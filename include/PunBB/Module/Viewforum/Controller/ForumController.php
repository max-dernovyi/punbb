<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Controller;

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
use PunBB\Module\Site\Visitor\TrackedTopics;
use PunBB\Module\Site\Visitor\VisitorInterface;
use PunBB\Module\Viewforum\Api\Data\ListedTopicInterface;
use PunBB\Module\Viewforum\Api\Data\ViewedForumInterface;
use PunBB\Module\Viewforum\Api\ForumTopicsInterface;
use PunBB\Module\Viewforum\Event\EmptyForumAssembling;
use PunBB\Module\Viewforum\Event\ForumViewEnding;
use PunBB\Module\Viewforum\Event\ForumViewRequested;
use PunBB\Module\Viewforum\Event\ForumViewStep;
use PunBB\Module\Viewforum\Event\TopicListHeadAssembling;
use PunBB\Module\Viewforum\Event\TopicRowAssembling;
use PunBB\Module\Viewforum\Event\TopicsListing;
use PunBB\Module\Viewforum\View\Paging;
use PunBB\Module\Viewforum\View\TopicRow;

/**
 * viewforum.php?id=: the topics of a forum the visitor may read, a page at a
 * time, sticky ones first, with what they have not read marked; a forum on
 * another site sends the visitor there.
 */
final class ForumController implements ControllerInterface {
	private const TEMPLATE = __DIR__.'/../templates/topics.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly ForumTopicsInterface $forums,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new ForumViewRequested());

		if (!$this->visitor->can(GroupPermission::ReadBoard))
			return $this->messages->respond($this->language->text('common', 'No view'), json: $request->xhr);

		$strings = $this->language->strings('forum');

		$id = isset($request->query['id']) && is_scalar($request->query['id']) ? intval($request->query['id']) : 0;
		$subscribes = !$this->visitor->isGuest() && $this->settings->enabled('o_subscriptions');
		$forum = $id >= 1 ? $this->forums->forum($id, $this->visitor->groupId(), $subscribes ? $this->visitor->id() : null) : null;

		if ($forum === null)
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$this->events->dispatch(new ForumViewStep(ForumViewStep::SELECTED, $forum));

		if ($forum->redirectUrl() !== '')
		{
			$this->events->dispatch(new ForumViewStep(ForumViewStep::REDIRECTING, $forum));

			return new Response('', 302, array('Location' => $forum->redirectUrl()));
		}

		$moderating = $this->visitor->isAdministrator() || ($this->visitor->can(GroupPermission::Moderate) && $this->moderates($forum));
		$mayPost = ($forum->groupPostsTopics() ?? $this->visitor->can(GroupPermission::PostTopics)) || $moderating;
		$tracked = !$this->visitor->isGuest() ? $this->visitor->trackedTopics() : new TrackedTopics();

		$paging = Paging::of($forum->topicCount(), $request->query['p'] ?? null, $this->visitor->topicsPerPage());
		$itemsInfo = $this->formatter->itemsInfo(self::string($strings, 'Topics'), $paging->offset + 1, $paging->last, $forum->topicCount(), $paging->pages);

		$this->events->dispatch(new ForumViewStep(ForumViewStep::PAGINATED, $forum, $moderating, $mayPost, $paging->page, $paging->pages));

		$topicIds = $this->forums->topicIds($forum->id(), $forum->sortsByPosted(), $paging->offset, $this->visitor->topicsPerPage());
		$topics = $topicIds !== array()
			? $this->forums->topics($topicIds, $forum->sortsByPosted(), !$this->visitor->isGuest() && $this->settings->enabled('o_show_dot') ? $this->visitor->id() : null)
			: array();

		$head = $this->head($forum, $paging, $mayPost, $strings);
		[$headOptions, $footOptions] = $this->options($forum, $topics !== array(), $moderating, $paging, $strings);

		return $this->pages->respond($head, fn (): array => array(
			'main' => $this->main($forum, $topics, $tracked, $paging, $itemsInfo, $headOptions, $footOptions, $strings),
		));
	}

	private function moderates(ViewedForumInterface $forum): bool {
		foreach ($forum->moderators() as $moderator)
			if ($moderator->username() === $this->visitor->username())
				return true;

		return false;
	}

	/** @param array<string, Html> $strings */
	private function head(ViewedForumInterface $forum, Paging $paging, bool $mayPost, array $strings): PageHead {
		$arguments = array($forum->id(), $this->urls->slug($forum->name()));
		$page = $this->language->text('common', 'Page');

		$navigation = array();
		if ($paging->page < $paging->pages)
		{
			$navigation['last'] = Html::format('<link rel="last" href="%s" title="%s %s" />', $this->urls->sublink('forum', 'page', $paging->pages, $arguments), $page, $paging->pages);
			$navigation['next'] = Html::format('<link rel="next" href="%s" title="%s %s" />', $this->urls->sublink('forum', 'page', $paging->page + 1, $arguments), $page, $paging->page + 1);
		}

		if ($paging->page > 1)
		{
			$navigation['prev'] = Html::format('<link rel="prev" href="%s" title="%s %s" />', $this->urls->sublink('forum', 'page', $paging->page - 1, $arguments), $page, $paging->page - 1);
			$navigation['first'] = Html::format('<link rel="first" href="%s" title="%s 1" />', $this->urls->link('forum', $arguments), $page);
		}

		if ($mayPost)
			$posting = Html::format('<p class="posting"><a class="newpost" href="%s"><span>%s</span></a></p>', $this->urls->link('new_topic', array($forum->id())), self::string($strings, 'Post topic'));
		else if ($this->visitor->isGuest())
			$posting = Html::format('<p class="posting">%s</p>', Html::format(self::string($strings, 'Login to post'),
				Html::format('<a href="%s">%s</a>', $this->urls->link('login'), $this->language->text('common', 'login')),
				Html::format('<a href="%s">%s</a>', $this->urls->link('register'), $this->language->text('common', 'register'))));
		else
			$posting = Html::format('<p class="posting">%s</p>', self::string($strings, 'No permission'));

		return new PageHead('viewforum',
			array(
				new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
				new Crumb($forum->name()),
			),
			indexable: true,
			page: $paging->page,
			pageCount: $paging->pages > 1 ? Html::format($this->language->text('common', 'Page info'), $paging->page, $paging->pages) : null,
			pagePost: array(
				'paging'	=> Html::format('<p class="paging"><span class="pages">%s</span> %s</p>', $this->language->text('common', 'Pages'), $this->urls->pagination($paging->pages, $paging->page, 'forum', $arguments)),
				'posting'	=> $posting,
			),
			navigation: $navigation,
			mainTitle: Html::format('<a class="permalink" href="%s" rel="bookmark" title="%s">%s</a>', $this->urls->link('forum', $arguments), self::string($strings, 'Permalink forum'), $forum->name())
		);
	}

	/**
	 * The links above the list and below it.
	 *
	 * @param array<string, Html> $strings
	 * @return array{Parts, Parts}
	 */
	private function options(ViewedForumInterface $forum, bool $listed, bool $moderating, Paging $paging, array $strings): array {
		$head = new Parts();
		$foot = new Parts();
		$userId = $this->visitor->id();

		if ($listed)
			$head->set('feed', Html::format('<span class="feed first-item"><a class="feed" href="%s">%s</a></span>', $this->urls->link('forum_rss', array($forum->id())), self::string($strings, 'RSS forum feed'))->html);

		if (!$this->visitor->isGuest() && $this->settings->enabled('o_subscriptions'))
		{
			if ($forum->isSubscribed())
				$head->set('unsubscribe', Html::format('<span><a class="sub-option" href="%s"><em>%s</em></a></span>',
					$this->urls->link('forum_unsubscribe', array($forum->id(), $this->tokens->token('forum_unsubscribe'.$forum->id().$userId))), self::string($strings, 'Unsubscribe'))->html);
			else
				$head->set('subscribe', Html::format('<span><a class="sub-option" href="%s" title="%s">%s</a></span>',
					$this->urls->link('forum_subscribe', array($forum->id(), $this->tokens->token('forum_subscribe'.$forum->id().$userId))), self::string($strings, 'Subscribe info'), self::string($strings, 'Subscribe'))->html);
		}

		if (!$this->visitor->isGuest() && $listed)
		{
			$foot->set('mark_read', Html::format('<span class="first-item"><a href="%s">%s</a></span>',
				$this->urls->link('mark_forum_read', array($forum->id(), $this->tokens->token('markforumread'.$forum->id().$userId))), self::string($strings, 'Mark forum read'))->html);

			if ($moderating)
				$foot->set('moderate', Html::format('<span%s><a href="%s">%s</a></span>', new Html($foot->isEmpty() ? ' class="first-item"' : ''),
					$this->urls->sublink('moderate_forum', 'page', $paging->page, array($forum->id())), self::string($strings, 'Moderate forum'))->html);
		}

		return array($head, $foot);
	}

	/**
	 * The list of topics, or the row saying there are none.
	 *
	 * @param list<ListedTopicInterface> $topics
	 * @param array<string, Html> $strings
	 */
	private function main(ViewedForumInterface $forum, array $topics, TrackedTopics $tracked, Paging $paging, Html $itemsInfo, Parts $headOptions, Parts $footOptions, array $strings): Html {
		$views = $this->settings->enabled('o_topic_views');

		$info = new Parts(array('replies' => Html::format('<strong class="info-replies">%s</strong>', self::string($strings, 'replies'))->html));
		if ($views)
			$info->set('views', Html::format('<strong class="info-views">%s</strong>', self::string($strings, 'views'))->html);
		$info->set('lastpost', Html::format('<strong class="info-lastpost">%s</strong>', self::string($strings, 'last post'))->html);

		$listHead = new TopicListHeadAssembling($forum, new Parts(array('title' => Html::format('<strong class="subject-title">%s</strong>', self::string($strings, 'Topics'))->html)), $info, $headOptions, $footOptions);
		$this->events->dispatch($listHead);

		$variables = array(
			'listed'		=> $topics !== array(),
			'id'			=> $forum->id(),
			'headOptions'	=> self::optionLinks($listHead, TopicListHeadAssembling::HEAD_OPTIONS),
			'footOptions'	=> self::optionLinks($listHead, TopicListHeadAssembling::FOOT_OPTIONS),
			'itemsInfo'		=> $itemsInfo,
			'viewsClass'	=> $views ? ' forum-views' : ' forum-noview',
			'summary'		=> Html::format(self::string($strings, 'Forum subtitle'), self::joined($listHead, TopicListHeadAssembling::SUBJECT, ' '), self::joined($listHead, TopicListHeadAssembling::INFO, ', ')),
			'empty'			=> self::string($strings, 'Empty forum'),
		);

		if ($topics !== array())
		{
			$listing = new TopicsListing($forum, $topics);
			$this->events->dispatch($listing);

			$rows = array();
			$itemCount = 0;
			foreach ($listing->topics() as $topic)
			{
				[$row, $itemCount] = $this->row($forum, $topic, $tracked, $paging, $itemCount, $views, $strings);
				$rows[] = $row;
			}

			$variables += array('before' => new Html($listing->markup()), 'rows' => $rows, 'emptySubject' => new Html(''));
		}
		else
		{
			$empty = new EmptyForumAssembling($forum, array(
				'title'	=> Html::format('<h3 class="hn">%s</h3>', self::string($strings, 'No topics'))->html,
				'desc'	=> Html::format('<p>%s</p>', self::string($strings, 'First topic nag'))->html,
			));
			$this->events->dispatch($empty);

			$lines = array();
			foreach ($empty->names() as $name)
				$lines[] = (string) $empty->entry($name);

			$variables += array('before' => new Html($empty->markup()), 'rows' => array(), 'emptySubject' => new Html(implode("\n\t\t\t\t", $lines)));
		}

		$body = $this->templates->render(self::TEMPLATE, $variables);

		$end = new ForumViewEnding($forum);
		$this->events->dispatch($end);

		return (new Html($listHead->markup().$body.$end->markup()))->trim();
	}

	/**
	 * A topic's row, built stage by stage as its observers change it.
	 *
	 * @param array<string, Html> $strings
	 * @return array{array<string, mixed>, int} what the template shows of the row, and the row count
	 */
	private function row(ViewedForumInterface $forum, ListedTopicInterface $topic, TrackedTopics $tracked, Paging $paging, int $itemCount, bool $views, array $strings): array {
		$row = new TopicRow();
		$subject = $topic->subject();
		$markup = '';

		// Each stage places what its observers added before the row, and counts on from where they left the rows
		$at = function (string $stage) use ($topic, &$subject, $paging, $row, &$itemCount, &$markup): void {
			$event = new TopicRowAssembling($stage, $topic, $subject, $paging->offset + $itemCount + ($stage === TopicRowAssembling::START ? 1 : 0), $row, $itemCount);
			$this->events->dispatch($event);

			$markup .= $event->markup();
			$itemCount = $event->itemCount();
		};

		$at(TopicRowAssembling::START);
		++$itemCount;

		if ($this->settings->enabled('o_censoring'))
			$subject = $this->formatter->censor($subject);

		$row->subject->set('starter', Html::format('<span class="item-starter">%s</span>', Html::format(self::string($strings, 'Topic starter'), $topic->poster()))->html);

		if ($topic->movedTo() !== null)
		{
			$row->status->set('moved', 'moved');
			$row->title->set('link', Html::format('<span class="item-status"><em class="moved">%s</em></span> <a href="%s">%s</a>',
				Html::format(self::string($strings, 'Item status'), self::string($strings, 'Moved')), $this->urls->link('topic', array($topic->movedTo(), $this->urls->slug($subject))), $subject)->html);

			$row->bodySubject->set('title', Html::format('<h3 class="hn"><span class="item-num">%s</span>%s</h3>', $this->formatter->number($paging->offset + $itemCount), new Html((string) $row->title->entry('link')))->html);

			$at(TopicRowAssembling::MOVED_SUBJECT);

			$row->bodyInfo->set('replies', Html::format('<li class="info-replies"><span class="label">%s</span></li>', self::string($strings, 'No replies info'))->html);
			if ($views)
				$row->bodyInfo->set('views', Html::format('<li class="info-views"><span class="label">%s</span></li>', self::string($strings, 'No views info'))->html);
			$row->bodyInfo->set('lastpost', Html::format('<li class="info-lastpost"><span class="label">%s</span></li>', self::string($strings, 'No lastpost info'))->html);
		}
		else
		{
			if (!$this->visitor->isGuest() && $this->settings->enabled('o_show_dot') && $topic->hasPosted())
			{
				$row->title->set('posted', Html::format('<span class="posted-mark">%s</span>', self::string($strings, 'You posted indicator'))->html);
				$row->status->set('posted', 'posted');
			}

			if ($topic->isSticky())
			{
				$row->titleStatus->set('sticky', Html::format('<em class="sticky">%s</em>', self::string($strings, 'Sticky'))->html);
				$row->status->set('sticky', 'sticky');
			}

			if ($topic->isClosed())
			{
				$row->titleStatus->set('closed', Html::format('<em class="closed">%s</em>', self::string($strings, 'Closed'))->html);
				$row->status->set('closed', 'closed');
			}

			$at(TopicRowAssembling::TITLE_STATUS);

			if (!$row->titleStatus->isEmpty())
				$row->title->set('status', Html::format('<span class="item-status">%s</span>', Html::format(self::string($strings, 'Item status'), new Html($row->titleStatus->join(', '))))->html);

			$row->title->set('link', Html::format('<a href="%s">%s</a>', $this->urls->link('topic', array($topic->id(), $this->urls->slug($subject))), $subject)->html);

			$at(TopicRowAssembling::TITLE);

			$row->bodySubject->set('title', Html::format('<h3 class="hn"><span class="item-num">%s</span> %s</h3>', $this->formatter->number($paging->offset + $itemCount), new Html($row->title->join(' ')))->html);

			if ($row->status->isEmpty())
				$row->status->set('normal', 'normal');

			$pages = $this->visitor->postsPerPage() > 0 ? (int) ceil(($topic->replyCount() + 1) / $this->visitor->postsPerPage()) : 1;
			if ($pages > 1)
				$row->nav->set('pages', Html::format('<span>%s&#160;</span>%s', self::string($strings, 'Pages'),
					$this->urls->pagination($pages, -1, 'topic', array($topic->id(), $this->urls->slug($subject)), $this->language->text('common', 'Page separator')))->html);

			if ($this->hasUnread($forum, $topic, $tracked))
			{
				$row->nav->set('new', Html::format('<em class="item-newposts"><a href="%s">%s</a></em>', $this->urls->link('topic_new_posts', array($topic->id(), $this->urls->slug($subject))), self::string($strings, 'New posts'))->html);
				$row->status->set('new', 'new');
			}

			$at(TopicRowAssembling::NAV);

			if (!$row->nav->isEmpty())
				$row->subject->set('nav', Html::format('<span class="item-nav">%s</span>', Html::format(self::string($strings, 'Topic navigation'), new Html($row->nav->join('&#160;&#160;'))))->html);

			$row->bodyInfo->set('replies', Html::format('<li class="info-replies"><strong>%s</strong> <span class="label">%s</span></li>', $this->formatter->number($topic->replyCount()), self::string($strings, $topic->replyCount() === 1 ? 'reply' : 'replies'))->html);
			if ($views)
				$row->bodyInfo->set('views', Html::format('<li class="info-views"><strong>%s</strong> <span class="label">%s</span></li>', $this->formatter->number($topic->viewCount()), self::string($strings, $topic->viewCount() === 1 ? 'view' : 'views'))->html);
			$row->bodyInfo->set('lastpost', Html::format('<li class="info-lastpost"><span class="label">%s</span> <strong><a href="%s">%s</a></strong> <cite>%s</cite></li>', self::string($strings, 'Last post'),
				$this->urls->link('post', array($topic->lastPostId())), $this->formatter->time($topic->lastPost(), TimeFormat::DateTime), Html::format(self::string($strings, 'by poster'), $topic->lastPoster()))->html);
		}

		$at(TopicRowAssembling::SUBJECT);

		$row->bodySubject->set('desc', '<p>'.$row->subject->join(' ').'</p>');

		$at(TopicRowAssembling::STATUS);

		$row->setStyle((($itemCount % 2 !== 0) ? ' odd' : ' even').($itemCount === 1 ? ' main-first-item' : '').(!$row->status->isEmpty() ? ' '.$row->status->join(' ') : ''));

		$at(TopicRowAssembling::ROW);

		return array(array(
			'before'	=> new Html($markup),
			'id'		=> $topic->id(),
			'style'		=> $row->style(),
			'status'	=> $row->status->join(' '),
			'subject'	=> new Html($row->bodySubject->join("\n\t\t\t\t")),
			'info'		=> new Html($row->bodyInfo->join("\n\t\t\t\t")),
		), $itemCount);
	}

	/**
	 * Whether the topic has a post since the visitor's last visit that they
	 * have not read, since they read the topic and since they marked the forum read.
	 */
	private function hasUnread(ViewedForumInterface $forum, ListedTopicInterface $topic, TrackedTopics $tracked): bool {
		if ($this->visitor->isGuest() || $topic->lastPost() <= $this->visitor->lastVisit())
			return false;

		$read = $tracked->topic($topic->id());
		$markedRead = $tracked->forum($forum->id());

		return ($read === 0 || $read < $topic->lastPost()) && ($markedRead === 0 || $markedRead < $topic->lastPost());
	}

	/** The parts of $group, joined with $glue. */
	private static function joined(TopicListHeadAssembling $event, string $group, string $glue): Html {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[] = (string) $event->entry($group, $name);

		return new Html(implode($glue, $parts));
	}

	/** The options of $group, joined with spaces; null when there are none. */
	private static function optionLinks(TopicListHeadAssembling $event, string $group): ?Html {
		return $event->names($group) !== array() ? self::joined($event, $group, ' ') : null;
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
