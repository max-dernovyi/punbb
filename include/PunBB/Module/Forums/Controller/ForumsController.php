<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Controller;

use PunBB\Module\Forums\Api\Data\CategoryInterface;
use PunBB\Module\Forums\Api\Data\ForumInterface;
use PunBB\Module\Forums\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Forums\Api\Data\ForumPositionInterface;
use PunBB\Module\Forums\Api\Data\GroupPermissionsInterface;
use PunBB\Module\Forums\Api\Data\ListedForumInterface;
use PunBB\Module\Forums\Api\ForumsInterface;
use PunBB\Module\Forums\Event\ForumChangeStep;
use PunBB\Module\Forums\Event\ForumDeletionRendering;
use PunBB\Module\Forums\Event\ForumFormRendering;
use PunBB\Module\Forums\Event\ForumsRendering;
use PunBB\Module\Forums\Event\ForumsRequested;
use PunBB\Module\Forums\Event\GroupPermissionRendering;
use PunBB\Module\Forums\Event\ListedForumRendering;
use PunBB\Module\Forums\Event\PermissionsComparing;
use PunBB\Module\Forums\Model\Forum;
use PunBB\Module\Forums\Model\ForumPermissions;
use PunBB\Module\Forums\Model\ForumPosition;
use PunBB\Module\Forums\View\FormView;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Site\Cache\QuickjumpCacheInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Removal\ForumContentsInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * admin/forums.php, for administrators: the form adding a forum and the list
 * of forums by category with their positions, the confirmation a deletion
 * asks for, the form editing a forum's details and each group's permissions
 * in it, and each change. A forum is deleted with everything posted in it.
 */
final class ForumsController implements ControllerInterface {
	private const TEMPLATE = __DIR__.'/../templates/forums.phtml';

	private const EDIT_TEMPLATE = __DIR__.'/../templates/edit.phtml';

	private const DELETE_TEMPLATE = __DIR__.'/../templates/delete.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ForumsInterface $forums,
		private readonly ForumContentsInterface $contents,
		private readonly QuickjumpCacheInterface $quickjump,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new ForumsRequested());

		if (!$this->visitor->isAdministrator())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$common = $this->language->strings('admin_common');
		$strings = $this->language->strings('admin_forums');

		if (isset($request->post['add_forum']))
			return $this->add($request, $strings);

		if (isset($request->query['del_forum']))
			return $this->delete($request, $common, $strings);

		if (isset($request->post['update_positions']))
			return $this->reorder($request, $strings);

		if (isset($request->query['edit_forum']))
			return $this->edit($request, $common, $strings);

		return $this->page($common, $strings);
	}

	/** @param array<string, Html> $strings */
	private function add(Request $request, array $strings): Response {
		$categoryId = isset($request->post['add_to_cat']) ? self::integer($request->post['add_to_cat']) : 0;
		if ($categoryId < 1)
			return $this->badRequest($request);

		$forum = new Forum(0, self::text($request->post['forum_name'] ?? null), categoryId: $categoryId, position: self::integer($request->post['position'] ?? 0));

		$this->events->dispatch(new ForumChangeStep(ForumChangeStep::ADDING, $forum));

		if ($forum->name() === '')
			return $this->messages->respond(self::string($strings, 'Must enter forum message'), json: $request->xhr);

		if (!$this->forums->categoryExists($categoryId))
			return $this->badRequest($request);

		$this->forums->add($forum);
		$this->quickjump->rebuild();

		return $this->done($request, new ForumChangeStep(ForumChangeStep::ADDED, $forum), self::string($strings, 'Forum added'), $this->urls->link('admin_forums'));
	}

	/**
	 * A forum deleted once the deletion is confirmed, with its topics, permissions and subscriptions; the confirmation until it is.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function delete(Request $request, array $common, array $strings): Response {
		$id = self::integer($request->query['del_forum']);
		if ($id < 1)
			return $this->badRequest($request);

		if (isset($request->post['del_forum_cancel']))
			return $this->redirects->respond($this->urls->link('admin_forums')->html, self::string($common, 'Cancel redirect'), $request->xhr);

		$confirmed = isset($request->post['del_forum_comply']);
		$forum = new Forum($id, '');

		$this->events->dispatch(new ForumChangeStep(ForumChangeStep::DELETING, $forum, confirmed: $confirmed));

		if (!$confirmed)
			return $this->confirm($request, $id, $common, $strings);

		if (function_exists('set_time_limit'))
			set_time_limit(0);

		$this->contents->empty($id);
		$this->contents->removeOrphans();
		$this->forums->remove($id);
		$this->forums->removePermissions($id);
		$this->forums->removeSubscriptions($id);
		$this->quickjump->rebuild();

		return $this->done($request, new ForumChangeStep(ForumChangeStep::DELETED, $forum, confirmed: true), self::string($strings, 'Forum deleted'), $this->urls->link('admin_forums'));
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function confirm(Request $request, int $id, array $common, array $strings): Response {
		$name = $this->forums->name($id);
		if ($name === null)
			return $this->badRequest($request);

		$forum = new Forum($id, $name);
		$action = new Html($this->urls->link('admin_forums')->html.'?del_forum='.$id);

		$crumbs = $this->crumbs($common);
		$crumbs[] = new Crumb(self::string($strings, 'Delete forum')->html);

		return $this->pages->respond(new PageHead('admin-forums', $crumbs, section: 'start', view: 'delete'), function () use ($forum, $action, $common, $strings): array {
			$start = new ForumDeletionRendering(ForumDeletionRendering::OUTPUT_START, $forum);
			$this->events->dispatch($start);

			$body = $this->templates->render(self::DELETE_TEMPLATE, array(
				'afo'		=> $strings,
				'common'	=> $common,
				'heading'	=> Html::format(self::string($strings, 'Confirm delete forum'), $forum->name()),
				'action'	=> $action,
				'token'		=> $this->tokens->token($action->html),
			));

			$end = new ForumDeletionRendering(ForumDeletionRendering::END, $forum);
			$this->events->dispatch($end);

			return array('main' => (new Html($start->markup().$body.$end->markup()))->trim());
		});
	}

	/**
	 * The positions as submitted, each stored when it changed. A forum added
	 * since the form was built is left alone; a negative position stops the
	 * update before anything is stored.
	 *
	 * @param array<string, Html> $strings
	 */
	private function reorder(Request $request, array $strings): Response {
		$submitted = $request->post['position'] ?? null;
		if (!is_array($submitted))
			return $this->badRequest($request);

		$positions = $byForum = array();
		foreach ($submitted as $forumId => $position)
		{
			if (!is_int($forumId))
				continue;

			$positions[] = new ForumPosition($forumId, self::integer($position));
			$byForum[$forumId] = self::integer($position);
		}

		$this->events->dispatch(new ForumChangeStep(ForumChangeStep::REORDERING, positions: $positions));

		$changed = array();
		foreach ($this->forums->positions() as $stored)
		{
			$position = $byForum[$stored->forumId()] ?? null;
			if ($position === null)
				continue;

			// admin_forums.php has no string of its own for it
			if ($position < 0)
				return $this->messages->respond($this->language->text('admin_categories', 'Must be integer'), json: $request->xhr);

			if ($stored->position() !== $position)
				$changed[] = new ForumPosition($stored->forumId(), $position);
		}

		if ($changed !== array())
			$this->forums->reposition(...$changed);

		$this->quickjump->rebuild();

		return $this->done($request, new ForumChangeStep(ForumChangeStep::REORDERED, positions: $positions), self::string($strings, 'Forums updated'), $this->urls->link('admin_forums'));
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function edit(Request $request, array $common, array $strings): Response {
		$id = self::integer($request->query['edit_forum']);
		if ($id < 1)
			return $this->badRequest($request);

		$this->events->dispatch(new ForumChangeStep(ForumChangeStep::SELECTED, new Forum($id, '')));

		$forum = $this->forums->find($id);
		if ($forum === null)
			return $this->badRequest($request);

		if (isset($request->post['save']))
			return $this->save($request, $forum, $strings);

		if (isset($request->post['revert_perms']))
			return $this->revert($request, $forum, $strings);

		return $this->form($forum, $common, $strings);
	}

	/**
	 * The forum's details as submitted and, when the form showed them, each group's permissions that changed.
	 *
	 * @param array<string, Html> $strings
	 */
	private function save(Request $request, ForumInterface $stored, array $strings): Response {
		$description = str_replace(array("\r\n", "\r"), "\n", self::text($request->post['forum_desc'] ?? null));
		$redirectUrl = isset($request->post['redirect_url']) && $stored->topicCount() === 0 ? self::text($request->post['redirect_url']) : '';

		$forum = new Forum(
			$stored->id(),
			self::text($request->post['forum_name'] ?? null),
			$description !== '' ? $description : null,
			$redirectUrl !== '' ? $redirectUrl : null,
			self::integer($request->post['sort_by'] ?? 0),
			self::integer($request->post['cat_id'] ?? 0),
			0,
			$stored->topicCount()
		);

		$this->events->dispatch(new ForumChangeStep(ForumChangeStep::SAVING, $forum));

		if ($forum->name() === '')
			return $this->messages->respond(self::string($strings, 'Must enter forum message'), json: $request->xhr);

		if ($forum->categoryId() < 1)
			return $this->badRequest($request);

		$this->forums->update($forum);

		if (isset($request->post['read_forum_old']))
			$this->savePermissions($request, $forum->id());

		$this->quickjump->rebuild();

		return $this->done($request, new ForumChangeStep(ForumChangeStep::SAVED, $forum), self::string($strings, 'Forum updated'), $this->urls->link('admin_forums_forum', array($forum->id())));
	}

	/**
	 * Each group's permissions as the form showed and submitted them. Those
	 * that changed are stored, or removed when they are the group's defaults.
	 */
	private function savePermissions(Request $request, int $forumId): void {
		$shownRead = self::map($request->post['read_forum_old'] ?? null);
		$shownReplies = self::map($request->post['post_replies_old'] ?? null);
		$shownTopics = self::map($request->post['post_topics_old'] ?? null);
		$read = self::map($request->post['read_forum_new'] ?? null);
		$replies = self::map($request->post['post_replies_new'] ?? null);
		$topics = self::map($request->post['post_topics_new'] ?? null);

		foreach ($this->forums->groupDefaults() as $group)
		{
			$groupId = $group->groupId();

			$shown = new ForumPermissions($groupId, self::integer($shownRead[$groupId] ?? 0) !== 0, self::integer($shownReplies[$groupId] ?? 0) !== 0, self::integer($shownTopics[$groupId] ?? 0) !== 0);
			$submitted = new ForumPermissions($groupId, $group->readsBoard() ? isset($read[$groupId]) : $shown->readForum(), isset($replies[$groupId]), isset($topics[$groupId]));

			$comparing = new PermissionsComparing($forumId, $group, ForumPermissions::defaults($group), $shown, $submitted);
			$this->events->dispatch($comparing);

			$submitted = $comparing->submitted();
			if (self::same($submitted, $comparing->shown()))
				continue;

			if (self::same($submitted, $comparing->defaults()))
				$this->forums->removeGroupPermissions($forumId, $groupId);
			else
			{
				$this->forums->updatePermissions($forumId, $submitted);

				if (!$this->forums->permissionsStored($forumId, $groupId))
					$this->forums->addPermissions($forumId, $submitted);
			}
		}
	}

	/** @param array<string, Html> $strings */
	private function revert(Request $request, ForumInterface $forum, array $strings): Response {
		$this->events->dispatch(new ForumChangeStep(ForumChangeStep::REVERTING, $forum));

		$this->forums->revertPermissions($forum->id());
		$this->quickjump->rebuild();

		return $this->done($request, new ForumChangeStep(ForumChangeStep::REVERTED, $forum), self::string($strings, 'Permissions reverted'), new Html($this->urls->link('admin_forums')->html.'?edit_forum='.$forum->id()));
	}

	/** Tells the next page what changed, and sends the browser to $url. */
	private function done(Request $request, ForumChangeStep $step, Html $message, Html $url): Response {
		$this->flash->info($message);

		$this->events->dispatch($step);

		return $this->redirects->respond($url->html, $message, $request->xhr);
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function form(ForumInterface $forum, array $common, array $strings): Response {
		$action = new Html($this->urls->link('admin_forums')->html.'?edit_forum='.$forum->id());

		$lines = array();
		if ($forum->redirectUrl() !== null && $forum->redirectUrl() !== '' && $forum->redirectUrl() !== '0')
			$lines['redirect'] = Html::format('<li><span>%s</span></li>', self::string($strings, 'Forum perms redirect info'))->html;

		$lines['read'] = Html::format('<li><span>%s</span></li>', self::string($strings, 'Forum perms read info'))->html;
		$lines['restore'] = Html::format('<li><span>%s</span></li>', self::string($strings, 'Forum perms restore info'))->html;
		$lines['groups'] = Html::format('<li><span>%s</span></li>', Html::format(self::string($strings, 'Forum perms groups info'), Html::format('<a href="%s">%s</a>', $this->urls->link('admin_groups'), self::string($strings, 'User groups'))))->html;
		$lines['admins'] = Html::format('<li><span>%s</span></li>', self::string($strings, 'Forum perms admins info'))->html;

		$view = new FormView(ForumFormRendering::POSITIONS, array(
			'afo'			=> $strings,
			'common'		=> $common,
			'heading'		=> Html::format(self::string($strings, 'Edit forum head'), $forum->name()),
			'action'		=> $action,
			'token'			=> $this->tokens->token($action->html),
			'name'			=> $forum->name(),
			'description'	=> $forum->description() ?? '',
			'sortBy'		=> $forum->sortBy(),
			'redirectUrl'	=> $forum->redirectUrl() ?? '',
			'hasTopics'		=> $forum->topicCount() !== 0,
			'redirects'		=> $forum->redirectUrl() !== null && $forum->redirectUrl() !== '',
		));

		$crumbs = $this->crumbs($common);
		$crumbs[] = new Crumb(self::string($strings, 'Edit forum')->html);

		return $this->pages->respond(new PageHead('admin-forums', $crumbs, section: 'start', view: 'edit'), fn (): array => array('main' => $this->formMain($view, $forum, $lines)));
	}

	/** @param array<string, string> $lines */
	private function formMain(FormView $view, ForumInterface $forum, array $lines): Html {
		$at = function (string $position) use ($view, $forum, &$lines): ForumFormRendering {
			[$groups, $items, $fields] = $view->counts();

			$event = new ForumFormRendering($position, $forum, $groups, $items, $fields, $position !== ForumFormRendering::END ? $lines : array());
			$this->events->dispatch($event);
			$view->place($event);

			if ($event->carriesLines())
			{
				$lines = array();
				foreach ($event->names() as $name)
					$lines[$name] = (string) $event->entry($name);
			}

			return $event;
		};

		$at(ForumFormRendering::OUTPUT_START);

		$at(ForumFormRendering::PRE_DETAILS_FIELDSET);
		$view->numberGroup('details_group');

		foreach (array('name' => ForumFormRendering::PRE_FORUM_NAME, 'description' => ForumFormRendering::PRE_FORUM_DESCRIP, 'category' => ForumFormRendering::PRE_FORUM_CAT) as $name => $position)
		{
			$at($position);
			$view->numberItem($name.'_item');
			$view->numberField($name.'_field');
		}

		$categories = array();
		foreach ($this->forums->assignableCategories() as $category)
			$categories[] = array('id' => $category->id(), 'name' => $category->name(), 'selected' => $category->id() === $forum->categoryId());

		$view->show('categories', $categories);

		$at(ForumFormRendering::PRE_FORUM_SORT_BY);
		$view->numberItem('sort_item');
		$view->numberField('sort_field');
		$at(ForumFormRendering::MODIFY_SORT_BY);

		$at(ForumFormRendering::PRE_FORUM_REDIRECT_URL);
		$view->numberItem('redirect_item');
		$view->numberField('redirect_field');

		$at(ForumFormRendering::PRE_DETAILS_FIELDSET_END);
		$at(ForumFormRendering::DETAILS_FIELDSET_END);
		$view->restartGroupsAndItems();
		$at(ForumFormRendering::PRE_PERMISSIONS_PART);

		$view->show('lines', Html::join("\n\t\t\t\t\t", array_map(static fn (string $line): Html => new Html($line), $lines)));
		$view->numberGroup('permissions_group');

		$groups = array();
		foreach ($this->forums->groupPermissions($forum->id()) as $group)
			$groups[] = $this->groupPermissions($view, $group);

		$view->show('groups', $groups);

		$body = $this->templates->render(self::EDIT_TEMPLATE, $view->variables());

		$end = $at(ForumFormRendering::END);

		return (new Html($view->markup(ForumFormRendering::OUTPUT_START)->html.$body.$end->markup()))->trim();
	}

	/** @return array<string, mixed> what the template shows of the group's fieldset */
	private function groupPermissions(FormView $view, GroupPermissionsInterface $group): array {
		// A forum's read permission is on unless it stores it off; posting is the group's unless the forum stores otherwise
		$shown = new ForumPermissions(
			$group->groupId(),
			$group->readForum() !== false,
			$group->postReplies() ?? $group->postsReplies(),
			$group->postTopics() ?? $group->postsTopics()
		);

		$at = function (string $position) use ($view, $group, $shown): Html {
			[$groups, $items, $fields] = $view->counts();

			$event = new GroupPermissionRendering($position, $group, $shown, $groups, $items, $fields);
			$this->events->dispatch($event);

			return $view->placeRow($event);
		};

		$listed = array(
			'id'				=> $group->groupId(),
			'title'				=> $group->groupTitle(),
			'read'				=> $shown->readForum(),
			'readsBoard'		=> $group->readsBoard(),
			'replies'			=> $shown->postReplies(),
			'repliesDefault'	=> $shown->postReplies() === $group->postsReplies(),
			'topics'			=> $shown->postTopics(),
			'topicsDefault'		=> $shown->postTopics() === $group->postsTopics(),
		);

		$listed['preFieldset'] = $at(GroupPermissionRendering::PRE_CUR_GROUP_PERMISSIONS_FIELDSET);
		$listed['item'] = $view->numberItem('permissions_item');

		$listed['preRead'] = $at(GroupPermissionRendering::PRE_CUR_GROUP_READ_FORUM_PERMISSION);
		$listed['readField'] = $view->numberField('read_field');

		$listed['preReplies'] = $at(GroupPermissionRendering::PRE_CUR_GROUP_POST_REPLIES_PERMISSION);
		$listed['repliesField'] = $view->numberField('replies_field');

		$listed['preTopics'] = $at(GroupPermissionRendering::PRE_CUR_GROUP_POST_TOPICS_PERMISSION);
		$listed['topicsField'] = $view->numberField('topics_field');

		$listed['postTopics'] = $at(GroupPermissionRendering::POST_CUR_GROUP_POST_TOPICS_PERMISSION);
		$listed['preFieldsetEnd'] = $at(GroupPermissionRendering::PRE_CUR_GROUP_PERMISSIONS_FIELDSET_END);
		$listed['fieldsetEnd'] = $at(GroupPermissionRendering::CUR_GROUP_PERMISSIONS_FIELDSET_END);

		return $listed;
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function page(array $common, array $strings): Response {
		$link = $this->urls->link('admin_forums');

		$view = new FormView(ForumsRendering::POSITIONS, array(
			'afo'			=> $strings,
			'addAction'		=> new Html($link->html.'?action=adddel'),
			'addToken'		=> $this->tokens->token($link->html.'?action=adddel'),
			'editAction'	=> new Html($link->html.'?action=edit'),
			'editToken'		=> $this->tokens->token($link->html.'?action=edit'),
		));

		return $this->pages->respond(new PageHead('admin-forums', $this->crumbs($common), section: 'start'), fn (): array => array('main' => $this->main($view, $link, $strings)));
	}

	/** @param array<string, Html> $strings */
	private function main(FormView $view, Html $link, array $strings): Html {
		$at = function (string $position) use ($view): ForumsRendering {
			[$groups, $items, $fields] = $view->counts();

			$event = new ForumsRendering($position, $groups, $items, $fields);
			$this->events->dispatch($event);
			$view->place($event);

			return $event;
		};

		$at(ForumsRendering::MAIN_OUTPUT_START);

		$at(ForumsRendering::PRE_ADD_FORUM_FIELDSET);
		$view->numberGroup('add_group');

		foreach (array('name' => ForumsRendering::PRE_NEW_FORUM_NAME, 'position' => ForumsRendering::PRE_NEW_FORUM_POSITION, 'category' => ForumsRendering::PRE_NEW_FORUM_CAT) as $name => $position)
		{
			$at($position);
			$view->numberItem($name.'_item');
			$view->numberField($name.'_field');
		}

		$view->show('addCategories', array_map(static fn (CategoryInterface $category): array => array('id' => $category->id(), 'name' => $category->name()), $this->forums->categories()));

		$at(ForumsRendering::PRE_ADD_FORUM_FIELDSET_END);
		$at(ForumsRendering::ADD_FORUM_FIELDSET_END);

		// Each category heads its forums and numbers its groups and items from one again
		$categories = $listed = array();
		$category = null;
		foreach ($this->forums->all() as $forum)
		{
			if ($category === null || $forum->categoryId() !== $category['id'])
			{
				if ($category !== null)
					$categories[] = $category + array('forums' => $listed);

				$view->restartGroupsAndItems();
				$category = array(
					'id'		=> $forum->categoryId(),
					'heading'	=> Html::format(self::string($strings, 'Forums in category'), $forum->categoryName()),
					'group'		=> $view->numberGroup('category_group'),
				);
				$listed = array();
			}

			$listed[] = $this->listedForum($view, $forum, $link, $strings);
		}

		if ($category !== null)
			$categories[] = $category + array('forums' => $listed);

		$view->show('categories', $categories);

		$body = $this->templates->render(self::TEMPLATE, $view->variables());

		$end = $at(ForumsRendering::END);

		return (new Html($view->markup(ForumsRendering::MAIN_OUTPUT_START)->html.$body.$end->markup()))->trim();
	}

	/**
	 * @param array<string, Html> $strings
	 * @return array<string, mixed> what the template shows of the forum's fieldset
	 */
	private function listedForum(FormView $view, ListedForumInterface $forum, Html $link, array $strings): array {
		$at = function (string $position) use ($view, $forum): Html {
			[$groups, $items, $fields] = $view->counts();

			$event = new ListedForumRendering($position, $forum, $groups, $items, $fields);
			$this->events->dispatch($event);

			return $view->placeRow($event);
		};

		$listed = array('id' => $forum->id(), 'name' => $forum->name(), 'position' => $forum->position());

		$listed['preFieldset'] = $at(ListedForumRendering::PRE_EDIT_CUR_FORUM_FIELDSET);
		$listed['item'] = $view->numberItem('forum_item');
		$listed['legend'] = Html::format(self::string($strings, 'Edit or delete'),
			Html::format('<a href="%s?edit_forum=%s">%s</a>', $link, $forum->id(), self::string($strings, 'Edit')),
			Html::format('<a href="%s?del_forum=%s">%s</a>', $link, $forum->id(), self::string($strings, 'Delete')));

		$listed['preName'] = $at(ListedForumRendering::PRE_EDIT_CUR_FORUM_NAME);

		$listed['prePosition'] = $at(ListedForumRendering::PRE_EDIT_CUR_FORUM_POSITION);
		$listed['positionField'] = $view->numberField('forum_position');

		$listed['preFieldsetEnd'] = $at(ListedForumRendering::PRE_EDIT_CUR_FORUM_FIELDSET_END);
		$listed['fieldsetEnd'] = $at(ListedForumRendering::EDIT_CUR_FORUM_FIELDSET_END);

		return $listed;
	}

	/**
	 * @param array<string, Html> $common
	 * @return list<Crumb>
	 */
	private function crumbs(array $common): array {
		return array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($common, 'Forum administration')->html, $this->urls->link('admin_index')),
			new Crumb(self::string($common, 'Start')->html, $this->urls->link('admin_index')),
			new Crumb(self::string($common, 'Forums')->html, $this->urls->link('admin_forums')),
		);
	}

	private function badRequest(Request $request): Response {
		return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
	}

	private static function same(ForumPermissionsInterface $a, ForumPermissionsInterface $b): bool {
		return $a->readForum() === $b->readForum() && $a->postReplies() === $b->postReplies() && $a->postTopics() === $b->postTopics();
	}

	/**
	 * A value the request carries as a list keyed by id; anything else is empty.
	 *
	 * @return array<array-key, mixed>
	 */
	private static function map(mixed $value): array {
		return is_array($value) ? $value : array();
	}

	/** A value the request carries as text, trimmed; anything else is empty. */
	private static function text(mixed $value): string {
		return is_string($value) ? (new Html($value))->trim()->html : '';
	}

	/** A value the request carries, as intval() took it. */
	private static function integer(mixed $value): int {
		return is_scalar($value) ? intval($value) : (int) ($value !== array() && $value !== null);
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
