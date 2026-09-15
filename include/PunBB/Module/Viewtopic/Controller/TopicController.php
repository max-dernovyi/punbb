<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;
use PunBB\Module\Viewtopic\Api\Data\TopicPostInterface;
use PunBB\Module\Viewtopic\Api\Data\ViewedTopicInterface;
use PunBB\Module\Viewtopic\Api\TopicPostsInterface;
use PunBB\Module\Viewtopic\Event\NewPostSeeking;
use PunBB\Module\Viewtopic\Event\PostAssembling;
use PunBB\Module\Viewtopic\Event\QuickPostRendering;
use PunBB\Module\Viewtopic\Event\TopicOptionsAssembling;
use PunBB\Module\Viewtopic\Event\TopicViewEnding;
use PunBB\Module\Viewtopic\Event\TopicViewRequested;
use PunBB\Module\Viewtopic\Event\TopicViewStep;
use PunBB\Module\Viewtopic\View\PostRow;

/**
 * viewtopic.php: the posts of a topic the visitor may read, a page at a time,
 * with the quick reply form below them. ?pid= shows the page a post is on;
 * action=new sends the visitor to the first post they have not read, and
 * action=last to the last post.
 */
final class TopicController implements ControllerInterface {
	private const POSTS = __DIR__.'/../templates/posts.phtml';

	private const QUICK_POST = __DIR__.'/../templates/quickpost.phtml';

	/** The account a guest posts under. */
	private const GUEST = 1;

	/** @var array<int, array{Parts, Parts, Parts}> poster id => what the page keeps of their identity, information and contacts for their next posts */
	private array $posters = array();

	/** @var array<int, Html> poster id => their signature, parsed */
	private array $signatures = array();

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly TopicPostsInterface $topics,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new TopicViewRequested());

		if (!$this->visitor->can(GroupPermission::ReadBoard))
			return $this->messages->respond($this->language->text('common', 'No view'), json: $request->xhr);

		$strings = $this->language->strings('topic');

		$action = $request->query['action'] ?? null;
		$id = isset($request->query['id']) && is_scalar($request->query['id']) ? intval($request->query['id']) : 0;
		$pid = isset($request->query['pid']) && is_scalar($request->query['pid']) ? intval($request->query['pid']) : 0;

		if ($id < 1 && $pid < 1)
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$requestedPage = $request->query['p'] ?? null;

		if ($pid !== 0)
		{
			$location = $this->topics->locate($pid);
			if ($location === null)
				return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

			$id = $location->topicId();
			$requestedPage = (int) ceil(($this->topics->countBefore($location->topicId(), $location->posted()) + 1) / max(1, $this->visitor->postsPerPage()));
		}
		else if ($action === 'new')
		{
			if (!$this->visitor->isGuest())
			{
				$lastViewed = $this->visitor->trackedTopics()->topics()[$id] ?? $this->visitor->lastVisit();

				$this->events->dispatch(new NewPostSeeking($id, $lastViewed));

				$first = $this->topics->firstPostAfter($id, $lastViewed);
				if ($first !== null && $first !== 0)
					return self::redirect($this->urls->link('post', array($first)));
			}

			return self::redirect($this->urls->link('topic_last_post', array($id)));
		}
		else if ($action === 'last')
		{
			$last = $this->topics->lastPostId($id);
			if ($last !== null && $last !== 0)
				return self::redirect($this->urls->link('post', array($last)));
		}

		$subscribes = !$this->visitor->isGuest() && $this->settings->enabled('o_subscriptions');
		$topic = $this->topics->topic($id, $this->visitor->groupId(), $subscribes ? $this->visitor->id() : null);

		if ($topic === null)
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$this->events->dispatch(new TopicViewStep(TopicViewStep::SELECTED, $topic));

		$moderating = $this->visitor->isAdministrator() || ($this->visitor->can(GroupPermission::Moderate) && $this->moderates($topic));
		$mayPost = (!$topic->isClosed() || $moderating) && ($this->repliesAllowed($topic) || $moderating);

		if (!$this->visitor->isGuest())
			$this->visitor->readTopic($id, time());

		$perPage = max(1, $this->visitor->postsPerPage());
		$total = $topic->replyCount() + 1;
		$pages = (int) ceil($total / $perPage);
		$page = !is_numeric($requestedPage) || $requestedPage <= 1 || $requestedPage > $pages ? 1 : (int) $requestedPage;
		$offset = $perPage * ($page - 1);
		$last = min($offset + $perPage, $total);
		$itemsInfo = $this->formatter->itemsInfo(self::string($strings, 'Posts'), $offset + 1, $last, $total, $pages);

		$subject = $this->settings->enabled('o_censoring') ? $this->formatter->censor($topic->subject()) : $topic->subject();

		$this->events->dispatch(new TopicViewStep(TopicViewStep::PAGINATED, $topic, $moderating, $mayPost, $page, $pages, $offset, $last, $subject));

		$head = $this->head($topic, $subject, $pid === 0, $mayPost, $page, $pages, $strings);

		return $this->pages->respond($head, function () use ($topic, $subject, $moderating, $page, $offset, $last, $perPage, $itemsInfo, $strings): array {
			$content = array('main' => $this->main($topic, $subject, $moderating, $page, $offset, $last, $perPage, $itemsInfo, $strings));

			if ($this->settings->enabled('o_quickpost') && !$this->visitor->isGuest() && $this->repliesAllowed($topic) && (!$topic->isClosed() || $moderating))
				$content['qpost'] = $this->quickPost($topic, $strings);

			if ($this->settings->enabled('o_topic_views'))
				$this->topics->countView($topic->id());

			return $content;
		});
	}

	private function moderates(ViewedTopicInterface $topic): bool {
		foreach ($topic->moderators() as $moderator)
			if ($moderator->username() === $this->visitor->username())
				return true;

		return false;
	}

	/** Whether the visitor's group may reply in the topic's forum, the forum deciding where it does. */
	private function repliesAllowed(ViewedTopicInterface $topic): bool {
		return $topic->groupPostsReplies() ?? $this->visitor->can(GroupPermission::PostReplies);
	}

	/** Sends the browser on, as the page did with a bare Location header. */
	private static function redirect(Html $link): Response {
		return new Response('', 302, array('Location' => str_replace('&amp;', '&', $link->html)));
	}

	/** @param array<string, Html> $strings */
	private function head(ViewedTopicInterface $topic, string $subject, bool $indexable, bool $mayPost, int $page, int $pages, array $strings): PageHead {
		$common = $this->language->strings('common');

		// The head's links were built before the subject was censored
		$raw = array($topic->id(), $this->urls->slug($topic->subject()));
		$shown = array($topic->id(), $this->urls->slug($subject));

		$navigation = array();
		if ($page < $pages)
		{
			$navigation['last'] = Html::format('<link rel="last" href="%s" title="%s %s" />', $this->urls->sublink('topic', 'page', $pages, $raw), self::string($common, 'Page'), $pages);
			$navigation['next'] = Html::format('<link rel="next" href="%s" title="%s %s" />', $this->urls->sublink('topic', 'page', $page + 1, $raw), self::string($common, 'Page'), $page + 1);
		}

		if ($page > 1)
		{
			$navigation['prev'] = Html::format('<link rel="prev" href="%s" title="%s %s" />', $this->urls->sublink('topic', 'page', $page - 1, $raw), self::string($common, 'Page'), $page - 1);
			$navigation['first'] = Html::format('<link rel="first" href="%s" title="%s 1" />', $this->urls->link('topic', $raw), self::string($common, 'Page'));
		}

		if ($mayPost)
			$posting = Html::format('<p class="posting"><a class="newpost" href="%s"><span>%s</span></a></p>', $this->urls->link('new_reply', array($topic->id())), self::string($strings, 'Post reply'));
		else if ($this->visitor->isGuest())
			$posting = Html::format('<p class="posting">%s</p>', Html::format(self::string($strings, 'Login to post'),
				Html::format('<a href="%s">%s</a>', $this->urls->link('login'), self::string($common, 'login')),
				Html::format('<a href="%s">%s</a>', $this->urls->link('register'), self::string($common, 'register'))));
		else if ($topic->isClosed())
			$posting = Html::format('<p class="posting">%s</p>', self::string($strings, 'Topic closed info'));
		else
			$posting = Html::format('<p class="posting">%s</p>', self::string($strings, 'No permission'));

		return new PageHead('viewtopic',
			array(
				new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
				new Crumb($topic->forumName(), $this->urls->link('forum', array($topic->forumId(), $this->urls->slug($topic->forumName())))),
				new Crumb($subject),
			),
			indexable: $indexable,
			page: $page,
			pageCount: $pages > 1 ? Html::format(self::string($common, 'Page info'), $page, $pages) : null,
			pagePost: array(
				'paging'	=> Html::format('<p class="paging"><span class="pages">%s</span> %s</p>', self::string($common, 'Pages'), $this->urls->pagination($pages, $page, 'topic', $shown)),
				'posting'	=> $posting,
			),
			navigation: $navigation,
			mainTitle: Html::format('%s<a class="permalink" href="%s" rel="bookmark" title="%s">%s</a>', new Html($topic->isClosed() ? self::string($strings, 'Topic closed')->html.' ' : ''),
				$this->urls->link('topic', $shown), self::string($strings, 'Permalink topic'), $subject)
		);
	}

	/**
	 * The options above and below the posts, and the posts.
	 *
	 * @param array<string, Html> $strings
	 */
	private function main(ViewedTopicInterface $topic, string $subject, bool $moderating, int $page, int $offset, int $last, int $perPage, Html $itemsInfo, array $strings): Html {
		[$headOptions, $footOptions] = $this->options($topic, $moderating, $page, $strings);

		$options = new TopicOptionsAssembling($topic, $moderating, $headOptions, $footOptions);
		$this->events->dispatch($options);

		$postIds = $this->topics->postIds($topic->id(), $offset, $perPage);
		$posts = $postIds !== array() ? $this->topics->posts($postIds) : array();

		$rows = array();
		$itemCount = 0;
		foreach ($posts as $post)
			$rows[] = $this->post($topic, $subject, $post, $moderating, $offset, $last, $itemCount, $strings);

		$body = $this->templates->render(self::POSTS, array(
			'headOptions'	=> self::optionLinks($options, TopicOptionsAssembling::HEAD_OPTIONS),
			'footOptions'	=> self::optionLinks($options, TopicOptionsAssembling::FOOT_OPTIONS),
			'itemsInfo'		=> $itemsInfo,
			'forumId'		=> $topic->forumId(),
			'posts'			=> $rows,
		));

		$end = new TopicViewEnding($topic);
		$this->events->dispatch($end);

		return (new Html($options->markup().$body.$end->markup()))->trim();
	}

	/**
	 * The links above the posts and, for a moderator, below them.
	 *
	 * @param array<string, Html> $strings
	 * @return array{Parts, Parts}
	 */
	private function options(ViewedTopicInterface $topic, bool $moderating, int $page, array $strings): array {
		$id = $topic->id();
		$userId = $this->visitor->id();

		$head = new Parts(array('rss' => Html::format('<span class="feed first-item"><a class="feed" href="%s">%s</a></span>', $this->urls->link('topic_rss', array($id)), self::string($strings, 'RSS topic feed'))->html));

		if (!$this->visitor->isGuest() && $this->settings->enabled('o_subscriptions'))
		{
			if ($topic->isSubscribed())
				$head->set('unsubscribe', Html::format('<span><a class="sub-option" href="%s"><em>%s</em></a></span>',
					$this->urls->link('unsubscribe', array($id, $this->tokens->token('unsubscribe'.$id.$userId))), self::string($strings, 'Unsubscribe'))->html);
			else
				$head->set('subscribe', Html::format('<span><a class="sub-option" href="%s" title="%s">%s</a></span>',
					$this->urls->link('subscribe', array($id, $this->tokens->token('subscribe'.$id.$userId))), self::string($strings, 'Subscribe info'), self::string($strings, 'Subscribe'))->html);
		}

		$foot = new Parts();
		if ($moderating)
		{
			$forumId = $topic->forumId();

			$foot->set('move', Html::format('<span class="first-item"><a class="mod-option" href="%s">%s</a></span>', $this->urls->link('move', array($forumId, $id)), self::string($strings, 'Move'))->html);
			$foot->set('delete', Html::format('<span><a class="mod-option" href="%s">%s</a></span>', $this->urls->link('delete', array($topic->firstPostId())), self::string($strings, 'Delete topic'))->html);

			$close = $topic->isClosed() ? array('open', 'Open') : array('close', 'Close');
			$foot->set('close', Html::format('<span><a class="mod-option" href="%s">%s</a></span>', $this->urls->link($close[0], array($forumId, $id, $this->tokens->token($close[0].$id.$userId))), self::string($strings, $close[1]))->html);

			$stick = $topic->isSticky() ? array('unstick', 'Unstick') : array('stick', 'Stick');
			$foot->set('sticky', Html::format('<span><a class="mod-option" href="%s">%s</a></span>', $this->urls->link($stick[0], array($forumId, $id, $this->tokens->token($stick[0].$id.$userId))), self::string($strings, $stick[1]))->html);

			if ($topic->replyCount() !== 0)
				$foot->set('moderate_topic', Html::format('<span><a class="mod-option" href="%s">%s</a></span>', $this->urls->sublink('moderate_topic', 'page', $page, array($forumId, $id)), self::string($strings, 'Moderate topic'))->html);
		}

		return array($head, $foot);
	}

	/**
	 * A post, built stage by stage as its observers change it.
	 *
	 * @param int $itemCount the posts of the page counted so far
	 * @param array<string, Html> $strings
	 * @return array<string, mixed> what the template shows of the post
	 */
	private function post(ViewedTopicInterface $topic, string $subject, TopicPostInterface $post, bool $moderating, int $offset, int $last, int &$itemCount, array $strings): array {
		$row = new PostRow();
		$before = '';

		$at = function (string $stage, PostRow $row) use ($topic, $post, $offset, &$itemCount): string {
			$event = new PostAssembling($stage, $topic, $post, $offset + $itemCount + ($stage === PostAssembling::START ? 1 : 0), $row, $itemCount);
			$this->events->dispatch($event);
			$itemCount = $event->itemCount();

			return $event->markup();
		};

		$before .= $at(PostAssembling::START, $row);
		++$itemCount;

		$member = $post->posterId() > self::GUEST;
		$first = $post->id() === $topic->firstPostId();
		$name = $member && $this->visitor->can(GroupPermission::ViewUsers)
			? Html::format('<a title="%s" href="%s">%s</a>', Html::format(self::string($strings, 'Go to profile'), $post->poster()), $this->urls->link('user', array($post->posterId())), $post->poster())
			: Html::format('<strong>%s</strong>', $post->poster());

		$row->postIdent->set('num', Html::format('<span class="post-num">%s</span>', $this->formatter->number($offset + $itemCount))->html);
		$row->postIdent->set('byline', Html::format('<span class="post-byline">%s</span>', Html::format(self::string($strings, $first ? 'Topic byline' : 'Reply byline'), $name))->html);
		$row->postIdent->set('link', Html::format('<span class="post-link"><a class="permalink" rel="bookmark" title="%s" href="%s">%s</a></span>',
			self::string($strings, 'Permalink post'), $this->urls->link('post', array($post->id())), $this->formatter->time($post->posted(), TimeFormat::DateTime))->html);

		if ($post->edited() !== null)
			$row->postIdent->set('edited', Html::format('<span class="post-edit">%s</span>', Html::format(self::string($strings, 'Last edited'), $post->editedBy() ?? '', $this->formatter->time($post->edited(), TimeFormat::DateTime)))->html);

		$before .= $at(PostAssembling::IDENT, $row);

		$kept = $this->posters[$post->posterId()] ?? null;

		if ($kept !== null)
			self::fill($row->authorIdent, $kept[0]);
		else
			$this->identify($row, $post, $member, $name);

		if ($kept !== null)
			self::fill($row->authorInfo, $kept[1]);
		else if ($member)
			$this->describe($row, $post, $strings);

		if ($this->visitor->isModerating())
			$row->authorInfo->set('ip', Html::format('<li><span>%s <a href="%s">%s</a></span></li>', self::string($strings, 'IP'), $this->urls->link('get_host', array($post->id())), $post->posterIp())->html);

		if ($this->settings->enabled('o_show_user_info'))
		{
			if ($kept !== null)
				self::fill($row->postContacts, $kept[2]);
			else
				$this->contacts($row, $post, $member, $strings);

			$before .= $at(PostAssembling::CONTACTS, $row);

			if (!$row->postContacts->isEmpty())
				$row->postOptions->set('contacts', '<p class="post-contacts">'.$row->postContacts->join(' ').'</p>');
		}

		$this->actions($row, $topic, $post, $moderating, $offset + $itemCount, $strings);

		$before .= $at(PostAssembling::ACTIONS, $row);

		if (!$row->postActions->isEmpty())
			$row->postOptions->set('actions', '<p class="post-actions">'.$row->postActions->join(' ').'</p>');

		$row->status->set('0', 'post');
		$row->status->set('1', $itemCount % 2 !== 0 ? 'odd' : 'even');
		if ($itemCount === 1)
			$row->status->set('firstpost', 'firstpost');
		if ($offset + $itemCount === $last)
			$row->status->set('lastpost', 'lastpost');
		$row->status->set($first ? 'topicpost' : 'replypost', $first ? 'topicpost' : 'replypost');

		$row->setSubject(Html::escape(sprintf(self::string($strings, $first ? 'Topic title' : 'Reply title')->html, $subject))->html);

		$row->message->set('message', $this->formatter->message($post->message(), $post->hidesSmilies())->html);

		if ($post->signature() !== null && $post->signature() !== '' && $this->visitor->showsSignatures() && $this->settings->enabled('o_signatures'))
		{
			$this->signatures[$post->posterId()] ??= $this->formatter->signature($post->signature());
			$row->message->set('signature', '<div class="sig-content"><span class="sig-line"><!-- --></span>'.$this->signatures[$post->posterId()]->html.'</div>');
		}

		$before .= $at(PostAssembling::ROW, $row);

		if ($member && $kept === null)
		{
			$cached = new PostRow(PostRow::copy($row->authorIdent), PostRow::copy($row->authorInfo), PostRow::copy($row->postContacts));
			$before .= $at(PostAssembling::CACHED, $cached);
			$this->posters[$post->posterId()] = array($cached->authorIdent, $cached->authorInfo, $cached->postContacts);
		}

		$shown = array(
			'before'		=> new Html($before),
			'id'			=> $post->id(),
			'status'		=> new Html($row->status->join(' ')),
			'ident'			=> new Html($row->postIdent->join(' ')),
			'online'		=> $post->isOnline(),
			'authorIdent'	=> new Html($row->authorIdent->join("\n\t\t\t\t\t\t")),
			'authorInfo'	=> new Html($row->authorInfo->join("\n\t\t\t\t\t\t")),
			'subject'		=> new Html($row->subject()),
			'message'		=> new Html($row->message->join("\n\t\t\t\t\t\t")),
		);

		// The options are placed after the markup inside the post, which may still change them
		$shown['entry'] = new Html($at(PostAssembling::ENTRY, $row));
		$shown['options'] = !$row->postOptions->isEmpty() ? new Html($row->postOptions->join("\n\t\t\t\t\t")) : null;

		return $shown;
	}

	/** What identifies the poster: their avatar, name, title and whether they are online; a guest's name and title. */
	private function identify(PostRow $row, TopicPostInterface $post, bool $member, Html $name): void {
		$title = Html::format('<li class="usertitle"><span>%s</span></li>', $this->formatter->memberTitle($post->poster(), $post->title() ?? '', $post->postCount(), $post->groupId(), $post->groupTitle()))->html;

		if (!$member)
		{
			$row->authorIdent->set('username', Html::format('<li class="username"><strong>%s</strong></li>', $post->poster())->html);
			$row->authorIdent->set('usertitle', $title);

			return;
		}

		if ($this->settings->enabled('o_avatars') && $this->visitor->showsAvatars())
		{
			$avatar = $this->formatter->avatar($post->posterId(), $post->avatar(), $post->avatarWidth(), $post->avatarHeight(), $post->poster());
			if ($avatar->html !== '')
				$row->authorIdent->set('avatar', '<li class="useravatar">'.$avatar->html.'</li>');
		}

		$row->authorIdent->set('username', Html::format('<li class="username">%s</li>', $name)->html);
		$row->authorIdent->set('usertitle', $title);
		$row->authorIdent->set('status', Html::format('<li class="userstatus"><span>%s</span></li>', $this->language->text('topic', $post->isOnline() ? 'Online' : 'Offline'))->html);
	}

	/**
	 * What describes a member who posted: where they are from, when they registered, how much they posted, and a moderator's note.
	 *
	 * @param array<string, Html> $strings
	 */
	private function describe(PostRow $row, TopicPostInterface $post, array $strings): void {
		if ($this->settings->enabled('o_show_user_info'))
		{
			if ($post->location() !== null && $post->location() !== '')
				$row->authorInfo->set('from', Html::format('<li><span>%s <strong>%s</strong></span></li>', self::string($strings, 'From'),
					$this->settings->enabled('o_censoring') ? $this->formatter->censor($post->location()) : $post->location())->html);

			$row->authorInfo->set('registered', Html::format('<li><span>%s <strong>%s</strong></span></li>', self::string($strings, 'Registered'), $this->formatter->time($post->registered(), TimeFormat::Date))->html);

			if ($this->settings->enabled('o_show_post_count') || $this->visitor->isModerating())
				$row->authorInfo->set('posts', Html::format('<li><span>%s <strong>%s</strong></span></li>', self::string($strings, 'Posts info'), $this->formatter->number($post->postCount()))->html);
		}

		if ($this->visitor->isModerating() && $post->adminNote() !== null && $post->adminNote() !== '')
			$row->authorInfo->set('note', Html::format('<li><span>%s <strong>%s</strong></span></li>', self::string($strings, 'Note'), $post->adminNote())->html);
	}

	/**
	 * How to reach the poster: their website and their email.
	 *
	 * @param array<string, Html> $strings
	 */
	private function contacts(PostRow $row, TopicPostInterface $post, bool $member, array $strings): void {
		$sends = $this->visitor->can(GroupPermission::SendEmail);
		$emailed = Html::format('%s<span>&#160;%s</span>', self::string($strings, 'E-mail'), $post->poster());

		if ($member)
		{
			if ($post->url() !== null && $post->url() !== '')
				$row->postContacts->set('url', Html::format('<span class="user-url%s"><a class="external" href="%s">%s</a></span>', self::first($row->postContacts),
					$this->settings->enabled('o_censoring') ? $this->formatter->censor($post->url()) : $post->url(),
					Html::format(self::string($strings, 'Visit website'), Html::format('<span>%s</span>', Html::format(self::string($strings, 'User possessive'), $post->poster()))))->html);

			if ((($post->emailSetting() === 0 && !$this->visitor->isGuest()) || $this->visitor->isModerating()) && $sends)
				$row->postContacts->set('email', Html::format('<span class="user-email%s"><a href="mailto:%s">%s</a></span>', self::first($row->postContacts), $post->email(), $emailed)->html);
			else if ($post->emailSetting() === 1 && !$this->visitor->isGuest() && $sends)
				$row->postContacts->set('email', Html::format('<span class="user-email%s"><a href="%s">%s</a></span>', self::first($row->postContacts), $this->urls->link('email', array($post->posterId())), $emailed)->html);
		}
		else if ($post->posterEmail() !== null && $post->posterEmail() !== '' && $this->visitor->isModerating() && $sends)
			$row->postContacts->set('email', Html::format('<span class="user-email%s"><a href="mailto:%s">%s</a></span>', self::first($row->postContacts), $post->posterEmail(), $emailed)->html);
	}

	/**
	 * What the visitor may do with the post: report it, delete, edit or quote it.
	 *
	 * @param int $number the post's place in the topic
	 * @param array<string, Html> $strings
	 */
	private function actions(PostRow $row, ViewedTopicInterface $topic, TopicPostInterface $post, bool $moderating, int $number, array $strings): void {
		$actions = $row->postActions;
		$numbered = fn (string $label): Html => Html::format('%s<span> %s %s</span>', self::string($strings, $label), self::string($strings, 'Post'), $this->formatter->number($number));
		$quote = fn (string $class): string => Html::format('<span class="%s%s"><a href="%s">%s</a></span>', $class, self::first($actions), $this->urls->link('quote', array($topic->id(), $post->id())), $numbered('Quote'))->html;
		$deleteTopic = fn (): string => Html::format('<span class="delete-topic%s"><a href="%s">%s</a></span>', self::first($actions), $this->urls->link('delete', array($topic->firstPostId())), self::string($strings, 'Delete topic'))->html;
		$deletePost = fn (): string => Html::format('<span class="delete-post%s"><a href="%s">%s</a></span>', self::first($actions), $this->urls->link('delete', array($post->id())), $numbered('Delete'))->html;
		$edit = fn (): string => Html::format('<span class="edit-post%s"><a href="%s">%s</a></span>', self::first($actions), $this->urls->link('edit', array($post->id())), $numbered('Edit'))->html;

		if ($this->visitor->isGuest())
		{
			if (!$topic->isClosed() && $this->repliesAllowed($topic))
				$actions->set('quote', $quote('report-post'));

			return;
		}

		$actions->set('report', Html::format('<span class="report-post%s"><a href="%s">%s</a></span>', self::first($actions), $this->urls->link('report', array($post->id())), $numbered('Report'))->html);

		if ($moderating)
		{
			$actions->set('delete', $number === 1 ? $deleteTopic() : $deletePost());
			$actions->set('edit', $edit());
			$actions->set('quote', $quote('quote-post'));

			return;
		}

		if ($topic->isClosed())
			return;

		if ($post->posterId() === $this->visitor->id())
		{
			if ($number === 1 && $this->visitor->can(GroupPermission::DeleteTopics))
				$actions->set('delete', $deleteTopic());

			if ($number > 1 && $this->visitor->can(GroupPermission::DeletePosts))
				$actions->set('delete', $deletePost());

			if ($this->visitor->can(GroupPermission::EditPosts))
				$actions->set('edit', $edit());
		}

		if ($this->repliesAllowed($topic))
			$actions->set('quote', $quote('quote-post'));
	}

	/**
	 * The quick reply form below the posts.
	 *
	 * @param array<string, Html> $strings
	 */
	private function quickPost(ViewedTopicInterface $topic, array $strings): Html {
		$common = $this->language->strings('common');
		$action = $this->urls->link('new_reply', array($topic->id()));

		$start = new QuickPostRendering(QuickPostRendering::OUTPUT_START, $topic, $action->html);
		$this->events->dispatch($start);

		$hidden = new Parts(array(
			'form_sent'		=> '<input type="hidden" name="form_sent" value="1" />',
			'form_user'		=> Html::format('<input type="hidden" name="form_user" value="%s" />', $this->visitor->isGuest() ? 'Guest' : $this->visitor->username())->html,
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $this->tokens->token($action->html))->html,
		));

		if (!$this->visitor->isGuest() && $this->settings->enabled('o_subscriptions') && ($this->visitor->subscribesOnReply() || $topic->isSubscribed()))
			$hidden->set('subscribe', '<input type="hidden" name="subscribe" value="1" />');

		$textOptions = new Parts();
		foreach (array('bbcode' => array('p_message_bbcode', 'BBCode'), 'img' => array('p_message_img_tag', 'Images'), 'smilies' => array('o_smilies', 'Smilies')) as $section => [$setting, $label])
			if ($this->settings->enabled($setting))
				$textOptions->set($section, Html::format('<span%s><a class="exthelp" href="%s" title="%s">%s</a></span>', new Html($textOptions->isEmpty() ? ' class="first-item"' : ''),
					$this->urls->link('help', array($section)), Html::format(self::string($common, 'Help page'), self::string($common, $label)), self::string($common, $label))->html);

		$display = new QuickPostRendering(QuickPostRendering::PRE_DISPLAY, $topic, $action->html, $hidden, new Parts(), $textOptions);
		$this->events->dispatch($display);

		$positions = array();
		foreach (array(QuickPostRendering::PRE_FIELDSET, QuickPostRendering::PRE_MESSAGE_BOX, QuickPostRendering::PRE_FIELDSET_END, QuickPostRendering::FIELDSET_END) as $position)
		{
			$event = new QuickPostRendering($position, $topic, $action->html);
			$this->events->dispatch($event);
			$positions[$position] = new Html($event->markup());
		}

		$body = $this->templates->render(self::QUICK_POST, array(
			'vt'			=> $strings,
			'common'		=> $common,
			'action'		=> $action,
			'attributes'	=> $display->names(QuickPostRendering::FORM_ATTRIBUTES) !== array() ? new Html(' '.self::joined($display, QuickPostRendering::FORM_ATTRIBUTES, ' ')->html) : new Html(''),
			'hidden'		=> self::joined($display, QuickPostRendering::HIDDEN_FIELDS, "\n\t\t\t\t"),
			'textOptions'	=> $display->names(QuickPostRendering::TEXT_OPTIONS) !== array() ? Html::format(self::string($common, 'You may use'), self::joined($display, QuickPostRendering::TEXT_OPTIONS, ' ')) : null,
			'positions'		=> $positions,
		));

		$end = new QuickPostRendering(QuickPostRendering::END, $topic, $action->html);
		$this->events->dispatch($end);

		return (new Html($start->markup().$display->markup().$body.$end->markup()))->trim();
	}

	/** The class a first link in $parts takes: none once there is one. */
	private static function first(Parts $parts): Html {
		return new Html($parts->isEmpty() ? ' first-item' : '');
	}

	/** Copies $kept into the empty $parts. */
	private static function fill(Parts $parts, Parts $kept): void {
		foreach ($kept->names() as $name)
			$parts->set($name, (string) $kept->entry($name));
	}

	/** The parts of $group, joined with $glue. */
	private static function joined(TopicOptionsAssembling|QuickPostRendering $event, string $group, string $glue): Html {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[] = (string) $event->entry($group, $name);

		return new Html(implode($glue, $parts));
	}

	/** The options of $group, joined with spaces; null when there are none. */
	private static function optionLinks(TopicOptionsAssembling $event, string $group): ?Html {
		return $event->names($group) !== array() ? self::joined($event, $group, ' ') : null;
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
