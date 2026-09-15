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
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Moderate\Api\Data\ListedTopicInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;
use PunBB\Module\Moderate\Api\Data\TargetForumInterface;
use PunBB\Module\Moderate\Api\ModeratedTopicsInterface;
use PunBB\Module\Moderate\Event\DeleteTopicsStep;
use PunBB\Module\Moderate\Event\MergeTopicsStep;
use PunBB\Module\Moderate\Event\ModerateActionRequested;
use PunBB\Module\Moderate\Event\ModeratedTopicAssembling;
use PunBB\Module\Moderate\Event\ModerationFormRendering;
use PunBB\Module\Moderate\Event\MoveTopicsStep;
use PunBB\Module\Moderate\Event\TargetForumRendering;
use PunBB\Module\Moderate\Event\TopicListRendering;
use PunBB\Module\Moderate\Event\TopicStateStep;
use PunBB\Module\Moderate\Indexing\PostIndexInterface;
use PunBB\Module\Moderate\Model\RedirectTopic;
use PunBB\Module\Moderate\Sync\BoardSyncInterface;
use PunBB\Module\Moderate\View\FormView;
use PunBB\Module\Moderate\View\Paging;
use PunBB\Module\Moderate\View\TopicRow;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\TrackedTopics;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * moderate.php?fid=: a forum's topics, each selectable, and moving, merging,
 * deleting, opening or closing those selected; and a topic's own links
 * opening, closing, sticking or unsticking it.
 */
final class TopicsModeration {
	private const TOPICS_TEMPLATE = __DIR__.'/../templates/topics.phtml';

	private const MOVE_TEMPLATE = __DIR__.'/../templates/move_topics.phtml';

	private const MERGE_TEMPLATE = __DIR__.'/../templates/merge_topics.phtml';

	private const DELETE_TEMPLATE = __DIR__.'/../templates/delete_topics.phtml';

	private const SELECT_ALL_SCRIPT = 'PUNBB.common.addDOMReadyEvent(PUNBB.common.initToggleCheckboxes);';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ConfirmPage $confirmations,
		private readonly ModeratedTopicsInterface $topics,
		private readonly BoardSyncInterface $sync,
		private readonly PostIndexInterface $index,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	/** @param array<string, Html> $misc */
	public function handle(Request $request, ModeratedForumInterface $forum, TrackedTopics $tracked, array $misc): Response {
		$common = $this->language->strings('common');
		$query = $request->query;
		$post = $request->post;

		if (isset($query['move_topics']) || isset($post['move_topics']) || isset($post['move_topics_to']))
			return $this->move($request, $forum, $common, $misc);

		if (isset($post['merge_topics']) || isset($post['merge_topics_comply']))
			return $this->merge($request, $forum, $common, $misc);

		if (isset($query['delete_topics']) || isset($post['delete_topics']) || isset($post['delete_topics_comply']))
			return $this->delete($request, $forum, $common, $misc);

		if (isset($query['open']) || isset($post['open']) || isset($query['close']) || isset($post['close']))
			return $this->openOrClose($request, $forum, $misc);

		if (isset($query['stick']))
			return $this->stick($request, $forum, true, $misc);

		if (isset($query['unstick']))
			return $this->stick($request, $forum, false, $misc);

		$this->events->dispatch(new ModerateActionRequested($forum));

		return $this->page($request, $forum, $tracked, $common, $misc);
	}

	/**
	 * Moving topics to the forum submitted; the form choosing it until it is.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $misc
	 */
	private function move(Request $request, ModeratedForumInterface $forum, array $common, array $misc): Response {
		$post = $request->post;

		if (isset($post['move_topics_to']))
		{
			$this->events->dispatch(new MoveTopicsStep(MoveTopicsStep::CONFIRMED));

			$topicIds = is_string($post['topics'] ?? null) && $post['topics'] !== '' ? array_map(Moderation::integer(...), explode(',', $post['topics'])) : array();
			$toId = Moderation::integer($post['move_to_forum'] ?? 0);
			$targetIds = array_map(static fn (TargetForumInterface $target): int => $target->forumId(), $this->topics->moveTargets($forum->id(), $this->visitor->groupId()));
			if ($topicIds === array() || !in_array($toId, $targetIds, true))
				return $this->badRequest($request);

			$toName = $this->topics->forumName($toId);
			if ($toName === null || !Moderation::found($toName) || $this->topics->countTopics($forum->id(), ...$topicIds) !== count($topicIds))
				return $this->badRequest($request);

			$this->topics->removeRedirects($toId, ...$topicIds);
			$this->topics->moveTopics($toId, ...$topicIds);

			$leaveRedirects = isset($post['with_redirect']);
			if ($leaveRedirects)
			{
				foreach ($topicIds as $topicId)
				{
					$moved = $this->topics->movedTopic($topicId);
					if ($moved !== null)
						$this->topics->addRedirects(new RedirectTopic($moved->poster(), $moved->subject(), $moved->posted(), $moved->lastPost(), $topicId, $forum->id()));
				}
			}

			$this->sync->forum($forum->id());
			$this->sync->forum($toId);

			$message = Moderation::string($misc, count($topicIds) > 1 ? 'Move topics redirect' : 'Move topic redirect');
			$this->flash->info($message);

			$this->events->dispatch(new MoveTopicsStep(MoveTopicsStep::MOVED, $topicIds, $toId, $toName, $leaveRedirects));

			return $this->redirects->respond($this->urls->link('forum', array($toId, $this->urls->slug($toName)))->html, $message, $request->xhr);
		}

		if (isset($post['move_topics']))
		{
			$topicIds = is_array($post['topics'] ?? null) ? array_map(Moderation::integer(...), array_values($post['topics'])) : array();
			if ($topicIds === array())
				return $this->messages->respond(Moderation::string($misc, 'No topics selected'), json: $request->xhr);
		}
		else
		{
			$topicId = Moderation::integer($request->query['move_topics'] ?? 0);
			if ($topicId < 1)
				return $this->badRequest($request);

			$topicIds = array($topicId);
		}

		$single = count($topicIds) === 1;

		$subject = null;
		if ($single)
		{
			if ($this->topics->subjectIn($topicIds[0], $forum->id()) === null)
				return $this->badRequest($request);

			$subject = $this->topics->subject($topicIds[0]);
			if ($subject === null || !Moderation::found($subject))
				return $this->badRequest($request);
		}

		$targets = $this->topics->moveTargets($forum->id(), $this->visitor->groupId());
		if ($targets === array())
			return $this->messages->respond(Moderation::string($misc, 'Nowhere to move'), json: $request->xhr);

		$crumb = Moderation::string($misc, $single ? 'Move topic' : 'Move topics');
		$heading = new Html($crumb->html.' '.Moderation::string($misc, 'To new forum')->html);

		$crumbs = $this->crumbs($forum);
		$crumbs[] = $subject !== null
			? new Crumb($subject, $this->urls->link('topic', array($topicIds[0], $this->urls->slug($subject))))
			: new Crumb(Moderation::string($misc, 'Moderate forum')->html, $this->urls->link('moderate_forum', array($forum->id())));
		$crumbs[] = new Crumb($crumb->html);

		return $this->confirmation(ModerationFormRendering::MOVE_TOPICS, self::MOVE_TEMPLATE, $forum, $topicIds, $crumbs, $heading, array('common' => $common, 'misc' => $misc, 'heading' => $heading, 'single' => $single),
			function (FormView $view, Closure $at) use ($targets, $forum): void {
				$at(ModerationFormRendering::PRE_FIELDSET);
				$view->numberGroup('group');

				$at(ModerationFormRendering::PRE_MOVE_TO_FORUM);
				$view->numberItem('forum_item');
				$view->numberField('forum_field');

				$options = array();
				$category = 0;
				foreach ($targets as $target)
				{
					$start = new TargetForumRendering(TargetForumRendering::START, $target);
					$this->events->dispatch($start);

					$opensGroup = $target->categoryId() !== $category;
					$option = array(
						'start'			=> new Html($start->markup()),
						'closesGroup'	=> $opensGroup && $category !== 0,
						'category'		=> $opensGroup ? $target->categoryName() : null,
						'id'			=> $target->forumId(),
						'name'			=> $target->forumId() !== $forum->id() ? $target->forumName() : null,
					);

					if ($opensGroup)
						$category = $target->categoryId();

					$end = new TargetForumRendering(TargetForumRendering::END, $target);
					$this->events->dispatch($end);

					$options[] = $option + array('end' => new Html($end->markup()));
				}

				$view->show('forums', $options);

				$at(ModerationFormRendering::PRE_REDIRECT_CHECKBOX);
				$view->numberItem('redirect_item');
				$view->numberField('redirect_field');

				$at(ModerationFormRendering::PRE_FIELDSET_END);
				$at(ModerationFormRendering::FIELDSET_END);
			});
	}

	/**
	 * Merging the topics selected into the oldest of them, once confirmed; the form confirming it until it is.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $misc
	 */
	private function merge(Request $request, ModeratedForumInterface $forum, array $common, array $misc): Response {
		$topicIds = Moderation::ids($request->post['topics'] ?? null);
		if ($topicIds === array())
			return $this->messages->respond(Moderation::string($misc, 'No topics selected'), json: $request->xhr);

		if (count($topicIds) === 1)
			return $this->messages->respond(Moderation::string($misc, 'Merge error'), json: $request->xhr);

		if (isset($request->post['merge_topics_comply']))
		{
			$this->events->dispatch(new MergeTopicsStep(MergeTopicsStep::CONFIRMED, $topicIds));

			$target = $this->topics->mergeTarget($forum->id(), ...$topicIds);
			$intoId = $target->lowestId();
			if ($target->topicCount() !== count($topicIds) || $intoId === null)
				return $this->badRequest($request);

			$leaveRedirects = isset($request->post['with_redirect']);

			$this->topics->redirectMerged($intoId, $leaveRedirects, ...$topicIds);
			$this->topics->mergePosts($intoId, ...$topicIds);
			$this->topics->removeMergedSubscriptions($intoId, ...$topicIds);

			if (!$leaveRedirects)
				$this->topics->removeMergedTopics($intoId, ...$topicIds);

			$this->sync->topic($intoId);
			$this->sync->forum($forum->id());

			$this->flash->info(Moderation::string($misc, 'Merge topics redirect'));

			$this->events->dispatch(new MergeTopicsStep(MergeTopicsStep::MERGED, $topicIds, $intoId, $leaveRedirects));

			return $this->redirects->respond($this->forumLink($forum)->html, Moderation::string($misc, 'Merge topics redirect'), $request->xhr);
		}

		$crumbs = $this->crumbs($forum);
		$crumbs[] = new Crumb(Moderation::string($misc, 'Moderate forum')->html, $this->urls->link('moderate_forum', array($forum->id())));
		$crumbs[] = new Crumb(Moderation::string($misc, 'Merge topics')->html);

		return $this->confirmation(ModerationFormRendering::MERGE_TOPICS, self::MERGE_TEMPLATE, $forum, $topicIds, $crumbs, null, array('common' => $common, 'misc' => $misc), function (FormView $view, Closure $at): void {
			$at(ModerationFormRendering::PRE_FIELDSET);
			$view->numberGroup('group');

			$at(ModerationFormRendering::PRE_REDIRECT_CHECKBOX);
			$view->numberItem('redirect_item');
			$view->numberField('redirect_field');

			$at(ModerationFormRendering::PRE_FIELDSET_END);
			$at(ModerationFormRendering::FIELDSET_END);
		});
	}

	/**
	 * Deleting the topics selected, once confirmed; the form confirming it until it is.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $misc
	 */
	private function delete(Request $request, ModeratedForumInterface $forum, array $common, array $misc): Response {
		$topicIds = Moderation::ids($request->post['topics'] ?? null);
		if ($topicIds === array())
			return $this->messages->respond(Moderation::string($misc, 'No topics selected'), json: $request->xhr);

		$multiple = count($topicIds) > 1;

		if (isset($request->post['delete_topics_comply']))
		{
			if (!isset($request->post['req_confirm']))
				return $this->redirects->respond($this->forumLink($forum)->html, Moderation::string($common, 'Cancel redirect'), $request->xhr);

			$this->events->dispatch(new DeleteTopicsStep(DeleteTopicsStep::CONFIRMED, $topicIds));

			if ($this->topics->countTopics($forum->id(), ...$topicIds) !== count($topicIds))
				return $this->badRequest($request);

			// The forums holding redirects to the topics lose them too
			$forumIds = array($forum->id(), ...$this->topics->redirectForums(...$topicIds));

			$this->topics->removeTopics(...$topicIds);
			$this->topics->removeSubscriptions(...$topicIds);

			$postIds = $this->topics->postIds(...$topicIds);
			if ($postIds !== array())
				$this->index->strip(...$postIds);

			$this->topics->removePosts(...$topicIds);

			foreach ($forumIds as $forumId)
				$this->sync->forum($forumId);

			$message = Moderation::string($misc, $multiple ? 'Delete topics redirect' : 'Delete topic redirect');
			$this->flash->info($message);

			$this->events->dispatch(new DeleteTopicsStep(DeleteTopicsStep::DELETED, $topicIds, $forumIds, $postIds));

			return $this->redirects->respond($this->forumLink($forum)->html, $message, $request->xhr);
		}

		$crumbs = $this->crumbs($forum);
		$crumbs[] = new Crumb(Moderation::string($misc, 'Moderate forum')->html, $this->urls->link('moderate_forum', array($forum->id())));
		$crumbs[] = new Crumb(Moderation::string($misc, $multiple ? 'Delete topics' : 'Delete topic')->html);

		return $this->confirmation(ModerationFormRendering::DELETE_TOPICS, self::DELETE_TEMPLATE, $forum, $topicIds, $crumbs, null, array('common' => $common, 'misc' => $misc, 'multi' => $multiple), function (FormView $view, Closure $at): void {
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
	 * Opening or closing the topics selected in the list, or the topic of a
	 * link once its token is checked.
	 *
	 * @param array<string, Html> $misc
	 */
	private function openOrClose(Request $request, ModeratedForumInterface $forum, array $misc): Response {
		$closing = !isset($request->query['open']) && !isset($request->post['open']);
		$listed = isset($request->post['open']) || isset($request->post['close']);

		$this->events->dispatch(new TopicStateStep(TopicStateStep::OPEN_CLOSE_SELECTED, $closing, listed: $listed));

		if ($listed)
		{
			$topicIds = is_array($request->post['topics'] ?? null) ? array_map(Moderation::integer(...), array_values($request->post['topics'])) : array();
			if ($topicIds === array())
				return $this->messages->respond(Moderation::string($misc, 'No topics selected'), json: $request->xhr);

			$this->topics->closeTopics($closing, $forum->id(), ...$topicIds);

			$message = Moderation::string($misc, count($topicIds) === 1 ? ($closing ? 'Close topic redirect' : 'Open topic redirect') : ($closing ? 'Close topics redirect' : 'Open topics redirect'));
			$this->flash->info($message);

			$this->events->dispatch(new TopicStateStep(TopicStateStep::LIST_CHANGED, $closing, $topicIds, listed: true));

			return $this->redirects->respond($this->urls->link('moderate_forum', array($forum->id()))->html, $message, $request->xhr);
		}

		$topicId = Moderation::integer($request->query[$closing ? 'close' : 'open'] ?? 0);
		if ($topicId < 1)
			return $this->badRequest($request);

		// A token posted with the request was checked on the way in; one in the link is checked here
		if (!isset($request->post['csrf_token']) && !$this->tokens->matches($request->query['csrf_token'] ?? null, ($closing ? 'close' : 'open').$topicId.$this->visitor->id()))
		{
			$confirmation = $this->confirmations->respond($request->post, $request->xhr);
			if ($confirmation !== null)
				return $confirmation;
		}

		$subject = $this->topics->subjectIn($topicId, $forum->id());
		if ($subject === null || !Moderation::found($subject))
			return $this->badRequest($request);

		$this->topics->closeTopics($closing, $forum->id(), $topicId);

		$message = Moderation::string($misc, $closing ? 'Close topic redirect' : 'Open topic redirect');
		$this->flash->info($message);

		$this->events->dispatch(new TopicStateStep(TopicStateStep::LINK_CHANGED, $closing, array($topicId), $subject));

		return $this->redirects->respond($this->urls->link('topic', array($topicId, $this->urls->slug($subject)))->html, $message, $request->xhr);
	}

	/**
	 * Sticking or unsticking the topic of a link, once its token is checked.
	 *
	 * @param array<string, Html> $misc
	 */
	private function stick(Request $request, ModeratedForumInterface $forum, bool $sticky, array $misc): Response {
		$action = $sticky ? 'stick' : 'unstick';

		$topicId = Moderation::integer($request->query[$action] ?? 0);
		if ($topicId < 1)
			return $this->badRequest($request);

		// A token posted with the request was checked on the way in; one in the link is checked here
		if (!isset($request->post['csrf_token']) && !$this->tokens->matches($request->query['csrf_token'] ?? null, $action.$topicId.$this->visitor->id()))
		{
			$confirmation = $this->confirmations->respond($request->post, $request->xhr);
			if ($confirmation !== null)
				return $confirmation;
		}

		$this->events->dispatch(new TopicStateStep($sticky ? TopicStateStep::STICK_SELECTED : TopicStateStep::UNSTICK_SELECTED, topicIds: array($topicId)));

		$subject = $this->topics->subjectIn($topicId, $forum->id());
		if ($subject === null || !Moderation::found($subject))
			return $this->badRequest($request);

		$this->topics->stickTopics($sticky, $forum->id(), $topicId);

		$message = Moderation::string($misc, $sticky ? 'Stick topic redirect' : 'Unstick topic redirect');
		$this->flash->info($message);

		$this->events->dispatch(new TopicStateStep($sticky ? TopicStateStep::STUCK : TopicStateStep::UNSTUCK, topicIds: array($topicId), subject: $subject));

		return $this->redirects->respond($this->urls->link('topic', array($topicId, $this->urls->slug($subject)))->html, $message, $request->xhr);
	}

	/**
	 * A form confirming a change of the topics selected, which $fill numbers once its start is placed.
	 *
	 * @param list<int> $topicIds
	 * @param list<Crumb> $crumbs
	 * @param array<string, mixed> $values
	 * @param Closure(FormView, Closure(string): Html): void $fill
	 */
	private function confirmation(string $form, string $template, ModeratedForumInterface $forum, array $topicIds, array $crumbs, ?Html $mainTitle, array $values, Closure $fill): Response {
		$action = $this->urls->link('moderate_forum', array($forum->id()));

		$hiddenFields = array(
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $this->tokens->token($action->html))->html,
			'topics'		=> Html::format('<input type="hidden" name="topics" value="%s" />', implode(',', $topicIds))->html,
		);

		return $this->pages->respond(new PageHead('dialogue', $crumbs, mainTitle: $mainTitle, view: $form),
			fn (): array => array('main' => Moderation::form($this->events, $this->templates, $form, $template, $forum->id(), $topicIds, $hiddenFields, array('action' => $action) + $values, $fill)));
	}

	/**
	 * The forum's topics, a page at a time.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $misc
	 */
	private function page(Request $request, ModeratedForumInterface $forum, TrackedTopics $tracked, array $common, array $misc): Response {
		if ($forum->topicCount() === 0)
			return $this->badRequest($request);

		$strings = $this->language->strings('forum');

		$paging = Paging::of($forum->topicCount(), $request->query['p'] ?? null, $this->visitor->topicsPerPage());
		$itemsInfo = $this->formatter->itemsInfo(Moderation::string($misc, 'Topics'), $paging->offset + 1, $paging->last, $forum->topicCount(), $paging->pages);

		$topics = $this->topics->topics($forum->id(), $forum->sortsByPosted(), $paging->offset, $this->visitor->topicsPerPage(),
			!$this->visitor->isGuest() && $this->settings->enabled('o_show_dot') ? $this->visitor->id() : null);

		$arguments = array($forum->id());
		$action = $this->urls->link('moderate_forum', $arguments);

		$selectAll = Html::format('<span  class="first-item"><span class="select-all js_link" data-check-form="mr-topic-actions-form">%s</span></span>', Moderation::string($misc, 'Select all'))->html;
		$headOptions = new Parts(array('select_all' => $selectAll));
		$footOptions = new Parts(array('select_all' => $selectAll));

		$crumbs = $this->crumbs($forum);
		$crumbs[] = new Crumb(Html::format(Moderation::string($misc, 'Moderate forum head'), $forum->name())->html);

		$head = new PageHead('modforum', $crumbs,
			page: $paging->page,
			pageCount: $paging->pages > 1 ? Html::format($this->language->text('common', 'Page info'), $paging->page, $paging->pages) : null,
			pagePost: array('paging' => Html::format('<p class="paging"><span class="pages">%s</span> %s</p>', Moderation::string($common, 'Pages'), $this->urls->pagination($paging->pages, $paging->page, 'moderate_forum', $arguments))),
			navigation: Moderation::navigation($this->urls, 'moderate_forum', $arguments, $paging, $this->language->text('common', 'Page'))
		);

		return $this->pages->respond($head, function (ChromeInterface $chrome) use ($forum, $topics, $tracked, $paging, $itemsInfo, $action, $headOptions, $footOptions, $strings, $common, $misc): array {
			$views = $this->settings->enabled('o_topic_views');

			$subject = new Parts(array('title' => Html::format('<strong class="subject-title">%s</strong>', Moderation::string($strings, 'Topics'))->html));
			$info = new Parts();
			if ($views)
				$info->set('views', Html::format('<strong class="info-views">%s</strong>', Moderation::string($strings, 'views'))->html);
			$info->set('replies', Html::format('<strong class="info-replies">%s</strong>', Moderation::string($strings, 'replies'))->html);
			$info->set('lastpost', Html::format('<strong class="info-lastpost">%s</strong>', Moderation::string($strings, 'last post'))->html);

			$modOptions = new Parts();

			$start = new TopicListRendering(TopicListRendering::OUTPUT_START, $forum, $subject, $info, $headOptions, $footOptions, $modOptions);
			$this->events->dispatch($start);

			$headLinks = Moderation::joined($headOptions);

			$rows = array();
			$itemCount = 0;
			$fieldCount = 0;
			foreach ($topics as $topic)
				$rows[] = $this->row($forum, $topic, $tracked, $paging, $itemCount, $fieldCount, $views, $strings, $common);

			$afterList = new TopicListRendering(TopicListRendering::POST_TOPIC_LIST, $forum, $subject, $info, $headOptions, $footOptions, $modOptions);
			$this->events->dispatch($afterList);

			$modOptions->set('mod_move', Html::format('<span class="submit first-item"><input type="submit" name="move_topics" value="%s" /></span>', Moderation::string($misc, 'Move'))->html);
			$modOptions->set('mod_delete', Html::format('<span class="submit"><input type="submit" name="delete_topics" value="%s" /></span>', Moderation::string($common, 'Delete'))->html);
			$modOptions->set('mod_merge', Html::format('<span class="submit"><input type="submit" name="merge_topics" value="%s" /></span>', Moderation::string($misc, 'Merge'))->html);
			$modOptions->set('mod_open', Html::format('<span class="submit"><input type="submit" name="open" value="%s" /></span>', Moderation::string($misc, 'Open'))->html);
			$modOptions->set('mod_close', Html::format('<span class="submit"><input type="submit" name="close" value="%s" /></span>', Moderation::string($misc, 'Close'))->html);

			$preModOptions = new TopicListRendering(TopicListRendering::PRE_MOD_OPTIONS, $forum, $subject, $info, $headOptions, $footOptions, $modOptions);
			$this->events->dispatch($preModOptions);

			$body = $this->templates->render(self::TOPICS_TEMPLATE, array(
				'headOptions'	=> $headLinks,
				'footOptions'	=> Moderation::joined($footOptions),
				'itemsInfo'		=> $itemsInfo,
				'action'		=> $action,
				'token'			=> $this->tokens->token($action->html),
				'viewsClass'	=> $views ? ' forum-views' : ' forum-noview',
				'summary'		=> Html::format(Moderation::string($strings, 'Forum subtitle'), new Html($subject->join(' ')), new Html($info->join(', '))),
				'forumId'		=> $forum->id(),
				'rows'			=> $rows,
				'afterList'		=> new Html($afterList->markup().$preModOptions->markup()),
				'modOptions'	=> new Html($modOptions->join(' ')),
			));

			$chrome->inlineScript(self::SELECT_ALL_SCRIPT);

			$end = new TopicListRendering(TopicListRendering::END, $forum, $subject, $info, $headOptions, $footOptions, $modOptions);
			$this->events->dispatch($end);

			return array('main' => (new Html($start->markup().$body.$end->markup()))->trim());
		});
	}

	/**
	 * A topic's row, built stage by stage as its observers change it.
	 *
	 * @param array<string, Html> $strings the forum pack
	 * @param array<string, Html> $common
	 * @return array<string, mixed> what the template shows of the row
	 */
	private function row(ModeratedForumInterface $forum, ListedTopicInterface $topic, TrackedTopics $tracked, Paging $paging, int &$itemCount, int &$fieldCount, bool $views, array $strings, array $common): array {
		$row = new TopicRow();
		$subject = $topic->subject();
		$markup = '';

		// Each stage places what its observers added before the row, and counts on from where they left the rows and checkboxes
		$at = function (string $stage) use ($topic, &$subject, $paging, $row, &$itemCount, &$fieldCount, &$markup): void {
			$event = new ModeratedTopicAssembling($stage, $topic, $subject, $paging->offset + $itemCount + ($stage === ModeratedTopicAssembling::START ? 1 : 0), $row, $itemCount, $fieldCount);
			$this->events->dispatch($event);

			$markup .= $event->markup();
			$itemCount = $event->itemCount();
			$fieldCount = $event->fieldCount();
		};

		$at(ModeratedTopicAssembling::START);
		++$itemCount;

		if ($this->settings->enabled('o_censoring'))
			$subject = $this->formatter->censor($subject);

		$number = $this->formatter->number($paging->offset + $itemCount);
		$row->subject->set('starter', Html::format('<span class="item-starter">%s</span>', Html::format(Moderation::string($strings, 'Topic starter'), $topic->poster()))->html);

		if ($topic->movedTo() !== null)
		{
			$row->status->set('moved', 'moved');
			$row->title->set('link', Html::format('<span class="item-status"><em class="moved">%s</em></span> <a href="%s">%s</a>',
				Html::format(Moderation::string($strings, 'Item status'), Moderation::string($strings, 'Moved')), $this->urls->link('topic', array($topic->movedTo(), $this->urls->slug($subject))), $subject)->html);

			$row->bodySubject->set('title', Html::format('<h3 class="hn"><span class="item-num">%s</span> <strong>%s</strong></h3>', $number, new Html((string) $row->title->entry('link')))->html);

			$at(ModeratedTopicAssembling::MOVED_SUBJECT);

			if ($views)
				$row->bodyInfo->set('views', Html::format('<li class="info-views"><span class="label">%s</span></li>', Moderation::string($strings, 'No views info'))->html);

			$row->bodyInfo->set('replies', Html::format('<li class="info-replies"><span class="label">%s</span></li>', Moderation::string($strings, 'No replies info'))->html);
			$row->bodyInfo->set('lastpost', Html::format('<li class="info-lastpost"><span class="label">%s</span></li>', Moderation::string($strings, 'No lastpost info'))->html);
			$row->bodyInfo->set('select', $this->select($topic, $subject, ++$fieldCount, $strings));

			$at(ModeratedTopicAssembling::MOVED_ROW);
		}
		else
		{
			if (!$this->visitor->isGuest() && $this->settings->enabled('o_show_dot') && $topic->hasPosted())
			{
				$row->title->set('posted', Html::format('<span class="posted-mark">%s</span>', Moderation::string($strings, 'You posted indicator'))->html);
				$row->status->set('posted', 'posted');
			}

			if ($topic->isSticky())
			{
				$row->titleStatus->set('sticky', Html::format('<em class="sticky">%s</em>', Moderation::string($strings, 'Sticky'))->html);
				$row->status->set('sticky', 'sticky');
			}

			if ($topic->isClosed())
			{
				$row->titleStatus->set('closed', Html::format('<em class="closed">%s</em>', Moderation::string($strings, 'Closed'))->html);
				$row->status->set('closed', 'closed');
			}

			$at(ModeratedTopicAssembling::TITLE_STATUS);

			if (!$row->titleStatus->isEmpty())
				$row->title->set('status', Html::format('<span class="item-status">%s</span>', Html::format(Moderation::string($strings, 'Item status'), new Html($row->titleStatus->join(', '))))->html);

			$row->title->set('link', Html::format('<a href="%s">%s</a>', $this->urls->link('topic', array($topic->id(), $this->urls->slug($subject))), $subject)->html);

			$at(ModeratedTopicAssembling::TITLE);

			$row->bodySubject->set('title', Html::format('<h3 class="hn"><span class="item-num">%s</span> %s</h3>', $number, new Html($row->title->join(' ')))->html);

			if ($row->status->isEmpty())
				$row->status->set('normal', 'normal');

			$pages = $this->visitor->postsPerPage() > 0 ? (int) ceil(($topic->replyCount() + 1) / $this->visitor->postsPerPage()) : 1;
			if ($pages > 1)
				$row->nav->set('pages', Html::format('<span>%s&#160;</span>%s', Moderation::string($strings, 'Pages'),
					$this->urls->pagination($pages, -1, 'topic', array($topic->id(), $this->urls->slug($subject)), Moderation::string($common, 'Page separator')))->html);

			if ($this->hasUnread($forum, $topic, $tracked))
			{
				$row->nav->set('new', Html::format('<em class="item-newposts"><a href="%s">%s</a></em>', $this->urls->link('topic_new_posts', array($topic->id(), $this->urls->slug($subject))), Moderation::string($strings, 'New posts'))->html);
				$row->status->set('new', 'new');
			}

			$at(ModeratedTopicAssembling::NAV);

			if (!$row->nav->isEmpty())
				$row->subject->set('nav', Html::format('<span class="item-nav">%s</span>', Html::format(Moderation::string($strings, 'Topic navigation'), new Html($row->nav->join('&#160;&#160;'))))->html);

			$row->bodyInfo->set('replies', Html::format('<li class="info-replies"><strong>%s</strong> <span class="label">%s</span></li>', $this->formatter->number($topic->replyCount()), Moderation::string($strings, $topic->replyCount() === 1 ? 'Reply' : 'Replies'))->html);

			if ($views)
				$row->bodyInfo->set('views', Html::format('<li class="info-views"><strong>%s</strong> <span class="label">%s</span></li>', $this->formatter->number($topic->viewCount()), Moderation::string($strings, $topic->viewCount() === 1 ? 'View' : 'Views'))->html);

			$row->bodyInfo->set('lastpost', Html::format('<li class="info-lastpost"><span class="label">%s</span> <strong><a href="%s">%s</a></strong> <cite>%s</cite></li>', Moderation::string($strings, 'Last post'),
				$this->urls->link('post', array($topic->lastPostId())), $this->formatter->time($topic->lastPost(), TimeFormat::DateTime), Html::format(Moderation::string($strings, 'by poster'), $topic->lastPoster()))->html);
			$row->bodyInfo->set('select', $this->select($topic, $subject, ++$fieldCount, $strings));

			$at(ModeratedTopicAssembling::NORMAL_ROW);
		}

		$row->bodySubject->set('desc', '<p>'.$row->subject->join(' ').'</p>');

		$at(ModeratedTopicAssembling::STATUS);

		$row->setStyle((($itemCount % 2 !== 0) ? ' odd' : ' even').($itemCount === 1 ? ' main-first-item' : '').(!$row->status->isEmpty() ? ' '.$row->status->join(' ') : ''));

		$at(ModeratedTopicAssembling::ROW);

		return array(
			'before'	=> new Html($markup),
			'id'		=> $topic->id(),
			'style'		=> $row->style(),
			'status'	=> $row->status->join(' '),
			'subject'	=> new Html($row->bodySubject->join("\n\t\t\t\t\t")),
			'info'		=> new Html($row->bodyInfo->join("\n\t\t\t\t\t")),
		);
	}

	/** @param array<string, Html> $strings */
	private function select(ListedTopicInterface $topic, string $subject, int $field, array $strings): string {
		return Html::format('<li class="info-select"><input id="fld%s" type="checkbox" name="topics[]" value="%s" /> <label for="fld%s">%s</label></li>', $field, $topic->id(), $field, Html::format(Moderation::string($strings, 'Select topic'), $subject))->html;
	}

	/**
	 * Whether the topic has a post since the visitor's last visit that they
	 * have not read, since they read the topic and since they marked the forum read.
	 */
	private function hasUnread(ModeratedForumInterface $forum, ListedTopicInterface $topic, TrackedTopics $tracked): bool {
		if ($this->visitor->isGuest() || $topic->lastPost() <= $this->visitor->lastVisit())
			return false;

		$read = $tracked->topic($topic->id());
		$markedRead = $tracked->forum($forum->id());

		return ($read === 0 || $read < $topic->lastPost()) && ($markedRead === 0 || $markedRead < $topic->lastPost());
	}

	/** @return list<Crumb> the board's crumb and the forum's */
	private function crumbs(ModeratedForumInterface $forum): array {
		return array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb($forum->name(), $this->forumLink($forum)),
		);
	}

	private function forumLink(ModeratedForumInterface $forum): Html {
		return $this->urls->link('forum', array($forum->id(), $this->urls->slug($forum->name())));
	}

	private function badRequest(Request $request): Response {
		return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
	}
}
