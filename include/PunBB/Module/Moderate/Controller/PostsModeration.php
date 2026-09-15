<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Controller;

use Closure;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Layout\Chrome\ChromeInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedPostInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedTopicInterface;
use PunBB\Module\Moderate\Api\ModeratedPostsInterface;
use PunBB\Module\Moderate\Event\ModeratedPostAssembling;
use PunBB\Module\Moderate\Event\ModerationFormRendering;
use PunBB\Module\Moderate\Event\PostListRendering;
use PunBB\Module\Moderate\Event\PostsModerationStep;
use PunBB\Module\Moderate\Indexing\PostIndexInterface;
use PunBB\Module\Moderate\Model\NewTopic;
use PunBB\Module\Moderate\Sync\BoardSyncInterface;
use PunBB\Module\Moderate\View\FormView;
use PunBB\Module\Moderate\View\Paging;
use PunBB\Module\Moderate\View\PostRow;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * moderate.php?fid=&tid=: a topic's posts, each selectable but the first, and
 * deleting those selected or splitting them off into a topic of their own.
 */
final class PostsModeration {
	private const POSTS_TEMPLATE = __DIR__.'/../templates/posts.phtml';

	private const DELETE_TEMPLATE = __DIR__.'/../templates/delete_posts.phtml';

	private const SPLIT_TEMPLATE = __DIR__.'/../templates/split_posts.phtml';

	private const SELECT_ALL_SCRIPT = 'PUNBB.common.addDOMReadyEvent(PUNBB.common.initToggleCheckboxes);';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ModeratedPostsInterface $posts,
		private readonly BoardSyncInterface $sync,
		private readonly PostIndexInterface $index,
		private readonly PostRulesInterface $rules,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	/** @param array<string, Html> $misc */
	public function handle(Request $request, ModeratedForumInterface $forum, array $misc): Response {
		$this->events->dispatch(new PostsModerationStep(PostsModerationStep::SELECTED));

		$topicId = Moderation::integer($request->query['tid'] ?? 0);
		$topic = $topicId >= 1 ? $this->posts->topic($topicId, $forum->id()) : null;
		if ($topic === null)
			return $this->badRequest($request);

		$common = $this->language->strings('common');
		$topicLink = $this->urls->link('topic', array($topic->id(), $this->urls->slug($topic->subject())));

		if (isset($request->post['delete_posts_cancel']))
			return $this->redirects->respond($topicLink->html, Moderation::string($common, 'Cancel redirect'), $request->xhr);

		if (isset($request->post['delete_posts']) || isset($request->post['delete_posts_comply']))
			return $this->deletePosts($request, $forum, $topic, $topicLink, $common, $misc);

		if (isset($request->post['split_posts']) || isset($request->post['split_posts_comply']))
			return $this->splitPosts($request, $forum, $topic, $topicLink, $common, $misc);

		return $this->page($request, $forum, $topic, $common, $misc);
	}

	/**
	 * Deleting the posts selected, once confirmed; the form confirming it until it is.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $misc
	 */
	private function deletePosts(Request $request, ModeratedForumInterface $forum, ModeratedTopicInterface $topic, Html $topicLink, array $common, array $misc): Response {
		$this->events->dispatch(new PostsModerationStep(PostsModerationStep::DELETE_SUBMITTED, $topic));

		$postIds = Moderation::ids($request->post['posts'] ?? null);
		if ($postIds === array())
			return $this->messages->respond(Moderation::string($misc, 'No posts selected'), json: $request->xhr);

		if (isset($request->post['delete_posts_comply']))
		{
			if (!isset($request->post['req_confirm']))
				return $this->redirects->respond($topicLink->html, Moderation::string($common, 'No confirm redirect'), $request->xhr);

			$this->events->dispatch(new PostsModerationStep(PostsModerationStep::DELETE_CONFIRMED, $topic, $postIds));

			if ($this->posts->countReplies($topic->id(), $topic->firstPostId(), ...$postIds) !== count($postIds))
				return $this->badRequest($request);

			$this->posts->deletePosts(...$postIds);
			$this->index->strip(...$postIds);

			$this->sync->topic($topic->id());
			$this->sync->forum($forum->id());

			$this->flash->info(Moderation::string($misc, 'Delete posts redirect'));

			$this->events->dispatch(new PostsModerationStep(PostsModerationStep::DELETED, $topic, $postIds));

			return $this->redirects->respond($topicLink->html, Moderation::string($misc, 'Delete posts redirect'), $request->xhr);
		}

		return $this->confirmation(ModerationFormRendering::DELETE_POSTS, self::DELETE_TEMPLATE, $forum, $topic, $postIds, Moderation::string($misc, 'Delete posts'), $common, $misc, function (FormView $view, Closure $at): void {
			$at(ModerationFormRendering::PRE_FIELDSET);
			$view->numberGroup('group');

			$at(ModerationFormRendering::PRE_CONFIRM_CHECKBOX);
			$view->numberItem('confirm_item');
			$view->numberField('confirm_field');

			$at(ModerationFormRendering::PRE_FIELDSET_END);
			$at(ModerationFormRendering::FIELDSET_END);
		});
	}

	/**
	 * Splitting the posts selected off into a topic of their own, once
	 * confirmed with its subject; the form asking for it until it is.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $misc
	 */
	private function splitPosts(Request $request, ModeratedForumInterface $forum, ModeratedTopicInterface $topic, Html $topicLink, array $common, array $misc): Response {
		$this->events->dispatch(new PostsModerationStep(PostsModerationStep::SPLIT_SUBMITTED, $topic));

		$postIds = Moderation::ids($request->post['posts'] ?? null);
		if ($postIds === array())
			return $this->messages->respond(Moderation::string($misc, 'No posts selected'), json: $request->xhr);

		if (isset($request->post['split_posts_comply']))
		{
			if (!isset($request->post['req_confirm']))
				return $this->redirects->respond($topicLink->html, Moderation::string($common, 'No confirm redirect'), $request->xhr);

			$post = $this->language->strings('post');

			$this->events->dispatch(new PostsModerationStep(PostsModerationStep::SPLIT_CONFIRMED, $topic, $postIds));

			if ($this->posts->countReplies($topic->id(), $topic->firstPostId(), ...$postIds) !== count($postIds))
				return $this->badRequest($request);

			$subject = Moderation::text($request->post['new_subject'] ?? null);

			if ($subject === '')
				return $this->messages->respond(Moderation::string($post, 'No subject'), json: $request->xhr);

			if (mb_strlen($subject) > $this->rules->subjectMaximumLength())
				return $this->messages->respond(Html::format(Moderation::string($post, 'Too long subject'), $this->rules->subjectMaximumLength()), json: $request->xhr);

			$first = $this->posts->firstPost(min($postIds));
			if ($first === null)
				return $this->badRequest($request);

			$this->posts->addTopics(new NewTopic($first->poster(), $subject, $first->posted(), $first->id(), $forum->id()));
			$newTopicId = $this->posts->lastTopicId();

			$this->posts->movePosts($newTopicId, ...$postIds);

			$this->sync->topic($newTopicId);
			$this->sync->topic($topic->id());
			$this->sync->forum($forum->id());

			$this->flash->info(Moderation::string($misc, 'Split posts redirect'));

			$this->events->dispatch(new PostsModerationStep(PostsModerationStep::SPLIT, $topic, $postIds, $newTopicId, $subject));

			return $this->redirects->respond($this->urls->link('topic', array($newTopicId, $this->urls->slug($subject)))->html, Moderation::string($misc, 'Split posts redirect'), $request->xhr);
		}

		return $this->confirmation(ModerationFormRendering::SPLIT_POSTS, self::SPLIT_TEMPLATE, $forum, $topic, $postIds, Moderation::string($misc, 'Split posts'), $common, $misc, function (FormView $view, Closure $at): void {
			$view->show('subjectLength', $this->rules->subjectMaximumLength());

			$at(ModerationFormRendering::PRE_FIELDSET);
			$view->numberGroup('group');
			$view->numberItem('item');

			$at(ModerationFormRendering::PRE_SUBJECT);
			$view->numberField('subject_field');

			// The page script numbered the confirmation's label past its checkbox
			$at(ModerationFormRendering::PRE_CONFIRM_CHECKBOX);
			$view->numberField('confirm_field');
			$view->numberField('confirm_label');

			$at(ModerationFormRendering::PRE_FIELDSET_END);
			$at(ModerationFormRendering::FIELDSET_END);
		});
	}

	/**
	 * A form confirming a change of the posts selected, which $fill numbers once its start is placed.
	 *
	 * @param list<int> $postIds
	 * @param array<string, Html> $common
	 * @param array<string, Html> $misc
	 * @param Closure(FormView, Closure(string): Html): void $fill
	 */
	private function confirmation(string $form, string $template, ModeratedForumInterface $forum, ModeratedTopicInterface $topic, array $postIds, Html $crumb, array $common, array $misc, Closure $fill): Response {
		$action = $this->urls->link('moderate_topic', array($forum->id(), $topic->id()));

		$hiddenFields = array(
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $this->tokens->token($action->html))->html,
			'posts'			=> Html::format('<input type="hidden" name="posts" value="%s" />', implode(',', $postIds))->html,
		);

		$head = new PageHead('dialogue', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb($forum->name(), $this->urls->link('forum', array($forum->id(), $this->urls->slug($forum->name())))),
			new Crumb($topic->subject(), $this->urls->link('topic', array($topic->id(), $this->urls->slug($topic->subject())))),
			new Crumb($crumb->html),
		), view: $form);

		return $this->pages->respond($head, fn (): array => array('main' => Moderation::form($this->events, $this->templates, $form, $template, $forum->id(), $postIds, $hiddenFields, array('action' => $action, 'common' => $common, 'misc' => $misc), $fill)));
	}

	/**
	 * The topic's posts, a page at a time.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $misc
	 */
	private function page(Request $request, ModeratedForumInterface $forum, ModeratedTopicInterface $topic, array $common, array $misc): Response {
		$strings = $this->language->strings('topic');

		$paging = Paging::of($topic->replyCount() + 1, $request->query['p'] ?? null, $this->visitor->postsPerPage());
		$itemsInfo = $this->formatter->itemsInfo(Moderation::string($misc, 'Posts'), $paging->offset + 1, $paging->last, $topic->replyCount() + 1, $paging->pages);

		$arguments = array($forum->id(), $topic->id());
		$subject = $this->settings->enabled('o_censoring') ? $this->formatter->censor($topic->subject()) : $topic->subject();
		$action = $this->urls->link('moderate_topic', $arguments);

		$selectAll = Html::format('<span  class="first-item"><span class="select-all js_link" data-check-form="mr-post-actions-form">%s</span></span>', Moderation::string($misc, 'Select all'))->html;
		$headOptions = new Parts(array('select_all' => $selectAll));
		$footOptions = new Parts(array('select_all' => $selectAll));

		$head = new PageHead('modtopic',
			array(
				new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
				new Crumb($forum->name(), $this->urls->link('forum', array($forum->id(), $this->urls->slug($forum->name())))),
				new Crumb($subject, $this->urls->link('topic', array($topic->id(), $this->urls->slug($subject)))),
				new Crumb(Moderation::string($strings, 'Moderate topic')->html),
			),
			page: $paging->page,
			pageCount: $paging->pages > 1 ? Html::format($this->language->text('common', 'Page info'), $paging->page, $paging->pages) : null,
			pagePost: array('paging' => Html::format('<p class="paging"><span class="pages">%s</span> %s</p>', Moderation::string($common, 'Pages'), $this->urls->pagination($paging->pages, $paging->page, 'moderate_topic', $arguments))),
			navigation: Moderation::navigation($this->urls, 'moderate_topic', $arguments, $paging, $this->language->text('common', 'Page')),
			mainTitle: Html::format(Moderation::string($misc, 'Moderate topic head'), $subject)
		);

		return $this->pages->respond($head, function (ChromeInterface $chrome) use ($forum, $topic, $subject, $paging, $itemsInfo, $action, $headOptions, $footOptions, $strings, $misc): array {
			$modOptions = new Parts();

			$start = new PostListRendering(PostListRendering::OUTPUT_START, $forum, $topic, $headOptions, $footOptions, $modOptions);
			$this->events->dispatch($start);

			$headLinks = Moderation::joined($headOptions);

			$posts = array();
			$itemCount = 0;
			foreach ($this->posts->posts($topic->id(), $paging->offset, $this->visitor->postsPerPage()) as $post)
				$posts[] = $this->post($topic, $post, $subject, $paging, $itemCount, $strings, $misc);

			$modOptions->set('del_posts', Html::format('<span class="submit first-item"><input type="submit" name="delete_posts" value="%s" /></span>', Moderation::string($misc, 'Delete posts'))->html);
			$modOptions->set('split_posts', Html::format('<span class="submit"><input type="submit" name="split_posts" value="%s" /></span>', Moderation::string($misc, 'Split posts'))->html);
			$modOptions->set('del_topic', Html::format('<span><a href="%s">%s</a></span>', $this->urls->link('delete', array($topic->firstPostId())), Moderation::string($misc, 'Delete whole topic'))->html);

			$preModOptions = new PostListRendering(PostListRendering::PRE_MOD_OPTIONS, $forum, $topic, $headOptions, $footOptions, $modOptions);
			$this->events->dispatch($preModOptions);

			$body = $this->templates->render(self::POSTS_TEMPLATE, array(
				'headOptions'		=> $headLinks,
				'footOptions'		=> Moderation::joined($footOptions),
				'itemsInfo'			=> $itemsInfo,
				'action'			=> $action,
				'token'				=> $this->tokens->token($action->html),
				'posts'				=> $posts,
				'beforeModOptions'	=> new Html($preModOptions->markup()),
				'modOptions'		=> new Html($modOptions->join(' ')),
			));

			$chrome->inlineScript(self::SELECT_ALL_SCRIPT);

			$end = new PostListRendering(PostListRendering::END, $forum, $topic, $headOptions, $footOptions, $modOptions);
			$this->events->dispatch($end);

			return array('main' => (new Html($start->markup().$body.$end->markup()))->trim());
		});
	}

	/**
	 * A post, built stage by stage as its observers change it.
	 *
	 * @param array<string, Html> $strings the topic pack
	 * @param array<string, Html> $misc
	 * @return array<string, mixed> what the template shows of the post
	 */
	private function post(ModeratedTopicInterface $topic, ModeratedPostInterface $post, string $subject, Paging $paging, int &$itemCount, array $strings, array $misc): array {
		$row = new PostRow();

		// Each stage places what its observers added, and counts on from where they left the posts
		$at = function (string $stage) use ($topic, $post, $paging, $row, &$itemCount): string {
			$event = new ModeratedPostAssembling($stage, $topic, $post, $paging->offset + $itemCount + ($stage === ModeratedPostAssembling::START ? 1 : 0), $row, $itemCount);
			$this->events->dispatch($event);

			$itemCount = $event->itemCount();

			return $event->markup();
		};

		$before = $at(ModeratedPostAssembling::START);
		++$itemCount;

		$number = $paging->offset + $itemCount;
		$first = $post->id() === $topic->firstPostId();
		$byline = Moderation::string($strings, $first ? 'Topic byline' : 'Reply byline');
		$profileLink = Html::format('<a title="%s" href="%s">%s</a>', Html::format(Moderation::string($strings, 'Go to profile'), $post->poster()), $this->urls->link('user', array($post->posterId())), $post->poster());
		$strongName = Html::format('<strong>%s</strong>', $post->poster());

		$row->postIdent->set('num', Html::format('<span class="post-num">%s</span>', $this->formatter->number($number))->html);
		$row->postIdent->set('byline', Html::format('<span class="post-byline">%s</span>', Html::format($byline, $post->posterId() > 1 && $this->visitor->can(GroupPermission::ViewUsers) ? $profileLink : $strongName))->html);
		$row->postIdent->set('link', Html::format('<span class="post-link"><a class="permalink" rel="bookmark" title="%s" href="%s">%s</a></span>', Moderation::string($strings, 'Permalink post'), $this->urls->link('post', array($post->id())), $this->formatter->time($post->posted(), TimeFormat::DateTime))->html);

		if ($post->edited() !== null)
			$row->postIdent->set('edited', Html::format('<span class="post-edit">%s</span>', Html::format(Moderation::string($strings, 'Last edited'), $post->editedBy(), $this->formatter->time($post->edited(), TimeFormat::DateTime)))->html);

		$before .= $at(ModeratedPostAssembling::IDENT);

		if (!$first)
			$row->setSelect(Html::format('<p class="item-select"><input type="checkbox" id="fld%s" name="posts[]" value="%s" /> <label for="fld%s">%s %s</label></p>', $post->id(), $post->id(), $post->id(), Moderation::string($misc, 'Select post'), $this->formatter->number($number))->html);

		$row->authorIdent->set('username', Html::format('<li class="username">%s</li>', $post->posterId() > 1 ? $profileLink : $strongName)->html);
		$row->authorIdent->set('usertitle', Html::format('<li class="usertitle"><span>%s</span></li>', $this->formatter->memberTitle($post->poster(), $post->posterTitle(), $post->posterPostCount(), $post->posterGroupId(), $post->posterGroupTitle()))->html);

		$row->status->set('0', 'post');
		$row->status->set('1', $itemCount % 2 !== 0 ? 'odd' : 'even');

		if ($itemCount === 1)
			$row->status->set('firstpost', 'firstpost');

		if ($number === $paging->last)
			$row->status->set('lastpost', 'lastpost');

		$row->status->set($first ? 'topicpost' : 'replypost', $first ? 'topicpost' : 'replypost');

		$row->setSubject(Html::escape(sprintf(Moderation::string($strings, $first ? 'Topic title' : 'Reply title')->html, $subject))->html);
		$row->message->set('message', $this->formatter->message($post->message(), $post->hidesSmilies())->html);

		$before .= $at(ModeratedPostAssembling::ROW);

		$preSelect = $at(ModeratedPostAssembling::PRE_ITEM_SELECT);
		$headOption = $at(ModeratedPostAssembling::HEAD_OPTION);
		$userIdent = $at(ModeratedPostAssembling::USER_IDENT);
		$entry = $at(ModeratedPostAssembling::ENTRY);

		return array(
			'before'		=> new Html($before),
			'id'			=> $post->id(),
			'status'		=> $row->status->join(' '),
			'ident'			=> new Html($row->postIdent->join(' ')),
			'preSelect'		=> new Html($preSelect),
			'select'		=> $row->select() !== '' ? new Html($row->select()) : null,
			'headOption'	=> new Html($headOption),
			'authorIdent'	=> new Html($row->authorIdent->join("\n\t\t\t\t\t\t")),
			'userIdent'		=> new Html($userIdent),
			'subject'		=> new Html($row->subject()),
			'message'		=> new Html($row->message->join("\n\t\t\t\t\t\t\t")),
			'entry'			=> new Html($entry),
		);
	}

	private function badRequest(Request $request): Response {
		return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
	}
}
