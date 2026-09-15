<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Groups\Api\Data\GroupInterface;
use PunBB\Module\Groups\Api\Data\GroupMembersInterface;
use PunBB\Module\Groups\Api\Data\ListedGroupInterface;
use PunBB\Module\Groups\Api\GroupsInterface;
use PunBB\Module\Groups\Event\DefaultGroupStep;
use PunBB\Module\Groups\Event\GroupChangeStep;
use PunBB\Module\Groups\Event\GroupFormRendering;
use PunBB\Module\Groups\Event\GroupRemovalRendering;
use PunBB\Module\Groups\Event\GroupRemovalStep;
use PunBB\Module\Groups\Event\GroupRowAssembling;
use PunBB\Module\Groups\Event\GroupsRendering;
use PunBB\Module\Groups\Event\GroupsRequested;
use PunBB\Module\Groups\Model\Group;
use PunBB\Module\Groups\View\FormView;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Site\Cache\ConfigCacheInterface;
use PunBB\Module\Site\Cache\QuickjumpCacheInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Moderation\ModeratorListsInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * admin/groups.php, for administrators: the forms adding a group and choosing
 * the default one with the list of groups, the form adding or editing a group,
 * the form moving a removed group's members, and each change.
 */
final class GroupsController implements ControllerInterface {
	private const TEMPLATE = __DIR__.'/../templates/groups.phtml';

	private const FORM_TEMPLATE = __DIR__.'/../templates/form.phtml';

	private const REMOVE_TEMPLATE = __DIR__.'/../templates/remove.phtml';

	/** What the guests' form has of a checkbox: the checkbox, only the position before it, or neither. */
	private const FOR_GUESTS = 'checkbox';

	private const POSITION_FOR_GUESTS = 'position';

	private const NOT_FOR_GUESTS = 'none';

	/** @var array<string, array{string, GroupPermission, string, ?string}> the moderation's checkboxes: field => the position before it, the permission, its label and help */
	private const MODERATOR_PERMISSIONS = array(
		'moderator'				=> array(GroupFormRendering::PRE_ALLOW_MODERATE_CHECKBOX, GroupPermission::Moderate, 'Allow moderate label', 'Allow moderate help'),
		'mod_edit_users'		=> array(GroupFormRendering::PRE_ALLOW_MOD_EDIT_PROFILES_CHECKBOX, GroupPermission::EditUsers, 'Allow mod edit profiles label', null),
		'mod_rename_users'		=> array(GroupFormRendering::PRE_ALLOW_MOD_EDIT_USERBANE_CHECKBOX, GroupPermission::RenameUsers, 'Allow mod edit username label', null),
		'mod_change_passwords'	=> array(GroupFormRendering::PRE_ALLOW_MOD_CHANGE_PASS_CHECKBOX, GroupPermission::ChangePasswords, 'Allow mod change pass label', null),
		'mod_ban_users'			=> array(GroupFormRendering::PRE_ALLOW_MOD_BAN_USERS_CHECKBOX, GroupPermission::BanUsers, 'Allow mod bans label', null),
	);

	/** @var array<string, array{string, GroupPermission, string, ?string, string}> the members' checkboxes, as above, and what the guests' form has of each */
	private const USER_PERMISSIONS = array(
		'read_board'	=> array(GroupFormRendering::PRE_ALLOW_READ_BOARD_CHECKBOX, GroupPermission::ReadBoard, 'Allow read board label', 'Allow read board help', self::FOR_GUESTS),
		'view_users'	=> array(GroupFormRendering::PRE_ALLOW_VIEW_USERS_CHECKBOX, GroupPermission::ViewUsers, 'Allow view users label', null, self::FOR_GUESTS),
		'post_replies'	=> array(GroupFormRendering::PRE_ALLOW_POST_REPLIES_CHECKBOX, GroupPermission::PostReplies, 'Allow post replies label', null, self::FOR_GUESTS),
		'post_topics'	=> array(GroupFormRendering::PRE_ALLOW_POST_TOPICS_CHECKBOX, GroupPermission::PostTopics, 'Allow post topics label', null, self::FOR_GUESTS),
		'edit_posts'	=> array(GroupFormRendering::PRE_ALLOW_EDIT_POSTS_CHECKBOX, GroupPermission::EditPosts, 'Allow edit posts label', null, self::POSITION_FOR_GUESTS),
		'delete_posts'	=> array(GroupFormRendering::PRE_ALLOW_DELETE_POSTS_CHECKBOX, GroupPermission::DeletePosts, 'Allow delete posts label', null, self::NOT_FOR_GUESTS),
		'delete_topics'	=> array(GroupFormRendering::PRE_ALLOW_DELETE_TOPICS_CHECKBOX, GroupPermission::DeleteTopics, 'Allow delete topics label', null, self::NOT_FOR_GUESTS),
		'set_title'		=> array(GroupFormRendering::PRE_ALLOW_SET_USER_TITLE_CHECKBOX, GroupPermission::SetTitle, 'Allow set user title label', null, self::NOT_FOR_GUESTS),
		'search'		=> array(GroupFormRendering::PRE_ALLOW_SEARCH_CHECKBOX, GroupPermission::Search, 'Allow use search label', null, self::FOR_GUESTS),
		'search_users'	=> array(GroupFormRendering::PRE_ALLOW_SEARCH_USERS_CHECKBOX, GroupPermission::SearchUsers, 'Allow search users label', null, self::FOR_GUESTS),
		'send_email'	=> array(GroupFormRendering::PRE_ALLOW_SEND_EMAIL_CHECKBOX, GroupPermission::SendEmail, 'Allow send email label', null, self::POSITION_FOR_GUESTS),
	);

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ConfirmPage $confirmations,
		private readonly GroupsInterface $groups,
		private readonly QuickjumpCacheInterface $quickjump,
		private readonly ConfigCacheInterface $config,
		private readonly ModeratorListsInterface $moderators,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new GroupsRequested());

		if (!$this->visitor->isAdministrator())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$common = $this->language->strings('admin_common');
		$strings = $this->language->strings('admin_groups');

		if (isset($request->post['add_group']) || isset($request->query['edit_group']))
			return $this->form($request, $common, $strings);

		if (isset($request->post['add_edit_group']))
			return $this->save($request, $strings);

		if (isset($request->post['set_default_group']))
			return $this->makeDefault($request, $strings);

		if (isset($request->query['del_group']))
			return $this->remove($request, $common, $strings);

		return $this->page($common, $strings);
	}

	/**
	 * The form adding a group based on the one posted, or editing the one asked for.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function form(Request $request, array $common, array $strings): Response {
		$adding = isset($request->post['add_group']);
		if ($adding)
		{
			$this->events->dispatch(new GroupChangeStep(GroupChangeStep::ADD_SELECTED));

			$id = self::integer($request->post['base_group'] ?? 0);
			$group = $this->groups->baseGroup($id);
		}
		else
		{
			$this->events->dispatch(new GroupChangeStep(GroupChangeStep::EDIT_SELECTED));

			$id = self::integer($request->query['edit_group']);
			if ($id < 1)
				return $this->badRequest($request);

			$group = $this->groups->group($id);
		}

		if ($group === null)
			return $this->badRequest($request);

		$action = $this->urls->link('admin_groups');
		$defaultGroup = !$adding && $this->defaultGroupId() === $group->id();
		$moderation = $group->id() !== GroupInterface::GUESTS && !$defaultGroup;

		$view = new FormView(GroupFormRendering::POSITIONS, array(
			'agr'				=> $strings,
			'common'			=> $common,
			'action'			=> $action,
			'token'				=> $this->tokens->token($action->html),
			'adding'			=> $adding,
			'id'				=> $id,
			'title'				=> $adding ? '' : $group->title(),
			'userTitle'			=> $group->userTitle() ?? '',
			'administrators'	=> $group->id() === GroupInterface::ADMINISTRATORS,
			'guests'			=> $group->id() === GroupInterface::GUESTS,
			'defaultGroup'		=> $defaultGroup,
			'moderation'		=> $moderation,
			'postFlood'			=> $group->postFlood(),
			'searchFlood'		=> $group->searchFlood(),
			'emailFlood'		=> $group->emailFlood(),
		));

		$crumbs = $this->crumbs($common);
		$crumbs[] = new Crumb(self::string($strings, $adding ? 'Add group heading' : 'Edit group heading')->html);

		return $this->pages->respond(new PageHead('admin-groups', $crumbs, section: 'users', view: 'form'), fn (): array => array('main' => $this->formMain($view, $group, $adding, $moderation, $strings)));
	}

	/**
	 * @param bool $moderation whether the form offers moderation, which the guests and the default group lack
	 * @param array<string, Html> $strings
	 */
	private function formMain(FormView $view, GroupInterface $group, bool $adding, bool $moderation, array $strings): Html {
		$at = function (string $position) use ($view, $group, $adding): Html {
			[$groups, $items, $fields] = $view->counts();

			$event = new GroupFormRendering($position, $group, $adding, $groups, $items, $fields);
			$this->events->dispatch($event);

			return $view->place($event);
		};

		$start = $at(GroupFormRendering::OUTPUT_START);

		$at(GroupFormRendering::PRE_BASIC_DETAILS_FIELDSET);
		$view->numberGroup('titles_group');

		$at(GroupFormRendering::PRE_GROUP_TITLE);
		$view->numberItem('title_item');
		$view->numberField('title_field');

		$at(GroupFormRendering::PRE_USER_TITLE);
		$view->numberItem('user_title_item');
		$view->numberField('user_title_field');

		$at(GroupFormRendering::PRE_BASIC_DETAILS_FIELDSET_END);
		$at(GroupFormRendering::BASIC_DETAILS_FIELDSET_END);

		// The administrators may do everything, so their form ends with the titles
		if ($group->id() !== GroupInterface::ADMINISTRATORS)
		{
			$guests = $group->id() === GroupInterface::GUESTS;

			$view->restartGroupsAndItems();
			$at(GroupFormRendering::PRE_PERMISSIONS_FIELDSET);
			$view->numberGroup('permissions_group');
			$at(GroupFormRendering::PRE_MOD_PERMISSIONS_FIELDSET);

			$moderatorPermissions = array();
			if ($moderation)
			{
				$view->numberItem('moderation_item');

				foreach (self::MODERATOR_PERMISSIONS as $name => [$position, $permission, $label, $help])
					$moderatorPermissions[] = array('pre' => $at($position), 'field' => $view->numberField($name), 'name' => $name, 'label' => $this->label($strings, $label, $help), 'checked' => $group->allows($permission->value));

				$at(GroupFormRendering::PRE_MOD_PERMISSIONS_FIELDSET_END);
				$at(GroupFormRendering::MOD_PERMISSIONS_FIELDSET_END);
			}

			$view->show('moderatorPermissions', $moderatorPermissions);
			$view->numberItem('user_item');

			$userPermissions = array();
			foreach (self::USER_PERMISSIONS as $name => [$position, $permission, $label, $help, $forGuests])
			{
				if ($guests && $forGuests === self::NOT_FOR_GUESTS)
					continue;

				$pre = $at($position);
				$field = !$guests || $forGuests === self::FOR_GUESTS ? $view->numberField($name) : null;
				$userPermissions[] = array('pre' => $pre, 'field' => $field, 'name' => $name, 'label' => $this->label($strings, $label, $help), 'checked' => $group->allows($permission->value));
			}

			$view->show('userPermissions', $userPermissions);

			$at(GroupFormRendering::PRE_USER_PERMISSIONS_FIELDSET_END);
			$at(GroupFormRendering::USER_PERMISSIONS_FIELDSET_END);

			$view->restartGroupsAndItems();
			$at(GroupFormRendering::PRE_FLOOD_FIELDSET);
			$view->numberGroup('flood_group');

			$at(GroupFormRendering::PRE_POST_INTERVAL);
			$view->numberItem('post_flood_item');
			$view->numberField('post_flood_field');

			$at(GroupFormRendering::PRE_SEARCH_INTERVAL);
			$view->numberItem('search_flood_item');
			$view->numberField('search_flood_field');

			$at(GroupFormRendering::PRE_EMAIL_INTERVAL);
			if (!$guests)
			{
				$at(GroupFormRendering::PRE_EMAIL_INTERVAL_FIELD);
				$view->numberItem('email_flood_item');
				$view->numberField('email_flood_field');
			}

			$at(GroupFormRendering::PRE_FLOOD_FIELDSET_END);
			$at(GroupFormRendering::FLOOD_FIELDSET_END);
		}

		$body = $this->templates->render(self::FORM_TEMPLATE, $view->variables());

		$end = $at(GroupFormRendering::END);

		return (new Html($start->html.$body.$end->html))->trim();
	}

	/**
	 * The group as the form submitted it, added or stored over the one edited.
	 * The administrators are allowed everything, and neither they nor the
	 * guests moderate.
	 *
	 * @param array<string, Html> $strings
	 */
	private function save(Request $request, array $strings): Response {
		$post = $request->post;
		$administrators = isset($post['group_id']) && $post['group_id'] == GroupInterface::ADMINISTRATORS;
		$moderator = ($post['moderator'] ?? null) == '1';

		$permissions = $moderator ? array(GroupPermission::Moderate) : array();
		foreach (self::MODERATOR_PERMISSIONS as $name => [, $permission])
			if ($permission !== GroupPermission::Moderate && $moderator && ($post[$name] ?? null) == '1')
				$permissions[] = $permission;

		foreach (self::USER_PERMISSIONS as $name => [, $permission])
			if (($post[$name] ?? null) == '1' || $administrators)
				$permissions[] = $permission;

		$userTitle = self::text($post['user_title'] ?? null);

		$group = new Group(
			self::integer($post['group_id'] ?? 0),
			self::text($post['req_title'] ?? null),
			$userTitle !== '' ? $userTitle : null,
			$permissions,
			isset($post['post_flood']) ? self::integer($post['post_flood']) : 0,
			isset($post['search_flood']) ? self::integer($post['search_flood']) : 0,
			isset($post['email_flood']) ? self::integer($post['email_flood']) : 0
		);

		if ($group->title() === '')
			return $this->messages->respond(self::string($strings, 'Must enter group message'), json: $request->xhr);

		$this->events->dispatch(new GroupChangeStep(GroupChangeStep::VALIDATED, $group));

		if (($post['mode'] ?? '') == 'add')
		{
			$this->events->dispatch(new GroupChangeStep(GroupChangeStep::ADDING, $group));

			if ($this->groups->titleTaken($group->title(), null))
				return $this->messages->respond(Html::format(self::string($strings, 'Already a group message'), $group->title()), json: $request->xhr);

			$this->groups->add($group);

			// The new group starts with the permissions the forums store for the group it is based on
			$this->groups->addForumPermissions($this->groups->lastAddedId(), ...$this->groups->forumPermissions(self::integer($post['base_group'] ?? 0)));
		}
		else
		{
			$this->events->dispatch(new GroupChangeStep(GroupChangeStep::EDITING, $group));

			if (in_array($group->id(), array(GroupInterface::ADMINISTRATORS, GroupInterface::GUESTS), true))
				$group = $group->without(GroupPermission::Moderate);

			if ($group->allows(GroupPermission::Moderate->value) && $this->defaultGroupId() === $group->id())
				return $this->messages->respond(self::string($strings, 'Moderator default group'), json: $request->xhr);

			if ($this->groups->titleTaken($group->title(), $group->id()))
				return $this->messages->respond(Html::format(self::string($strings, 'Already a group message'), $group->title()), json: $request->xhr);

			$this->groups->update($group);

			// Its members may have been listed as moderators of a forum
			if (!$group->allows(GroupPermission::Moderate->value))
				$this->moderators->clean();
		}

		$this->quickjump->rebuild();

		$done = self::string($strings, ($post['mode'] ?? '') == 'edit' ? 'Group edited' : 'Group added');
		$this->flash->info($done);

		$this->events->dispatch(new GroupChangeStep(GroupChangeStep::SAVED, $group));

		return $this->redirects->respond($this->urls->link('admin_groups')->html, $done, $request->xhr);
	}

	/**
	 * The group new users join, which neither administers, is the guests nor moderates.
	 *
	 * @param array<string, Html> $strings
	 */
	private function makeDefault(Request $request, array $strings): Response {
		$id = self::integer($request->post['default_group'] ?? 0);

		$this->events->dispatch(new DefaultGroupStep(DefaultGroupStep::SETTING, $id));

		if ($id === GroupInterface::ADMINISTRATORS || $id === GroupInterface::GUESTS || !$this->groups->isDefaultCandidate($id))
			return $this->badRequest($request);

		$this->groups->makeDefault($id);
		$this->config->rebuild();

		$this->flash->info(self::string($strings, 'Default group set'));

		$this->events->dispatch(new DefaultGroupStep(DefaultGroupStep::SET, $id));

		return $this->redirects->respond($this->urls->link('admin_groups')->html, self::string($strings, 'Default group set'), $request->xhr);
	}

	/**
	 * A group without members removed, or one with members once they have a
	 * group to move to; the form asking for it until they do.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function remove(Request $request, array $common, array $strings): Response {
		$id = self::integer($request->query['del_group']);
		if ($id <= GroupInterface::GUESTS)
			return $this->badRequest($request);

		if (isset($request->post['del_group_cancel']))
			return $this->redirects->respond($this->urls->link('admin_groups')->html, self::string($common, 'Cancel redirect'), $request->xhr);

		if ($id === $this->defaultGroupId())
			return $this->messages->respond(self::string($strings, 'Cannot remove default group'), json: $request->xhr);

		$this->events->dispatch(new GroupRemovalStep(GroupRemovalStep::SELECTED, $id));

		$members = $this->groups->members($id);
		if ($members !== null && !isset($request->post['del_group']))
			return $this->removal($id, $members, $common, $strings);

		// A token posted with the request was checked on the way in; one in the link is checked here
		if (!isset($request->post['csrf_token']) && !$this->tokens->matches($request->query['csrf_token'] ?? null, 'del_group'.$id.$this->visitor->id()))
		{
			$confirmation = $this->confirmations->respond($request->post, $request->xhr);
			if ($confirmation !== null)
				return $confirmation;
		}

		$movedTo = isset($request->post['del_group']) ? self::integer($request->post['move_to_group'] ?? 0) : null;

		$this->events->dispatch(new GroupRemovalStep(GroupRemovalStep::REMOVING, $id, $movedTo));

		if ($movedTo !== null)
			$this->groups->moveMembers($movedTo, $id);

		$this->groups->remove($id);
		$this->groups->removeForumPermissions($id);
		$this->moderators->clean();
		$this->quickjump->rebuild();

		$this->flash->info(self::string($strings, 'Group removed'));

		$this->events->dispatch(new GroupRemovalStep(GroupRemovalStep::REMOVED, $id, $movedTo));

		return $this->redirects->respond($this->urls->link('admin_groups')->html, self::string($strings, 'Group removed'), $request->xhr);
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function removal(int $id, GroupMembersInterface $members, array $common, array $strings): Response {
		$action = new Html($this->urls->link('admin_groups')->html.'?del_group='.$id);

		$view = new FormView(GroupRemovalRendering::POSITIONS, array(
			'agr'		=> $strings,
			'common'	=> $common,
			'heading'	=> Html::format(self::string($strings, 'Remove group head'), $members->title(), $members->count()),
			'action'	=> $action,
			'token'		=> $this->tokens->token($action->html),
		));

		$crumbs = $this->crumbs($common);
		$crumbs[] = new Crumb(self::string($strings, 'Remove group')->html);

		return $this->pages->respond(new PageHead('admin-groups', $crumbs, section: 'users', view: 'remove'), function () use ($view, $id, $members): array {
			$at = function (string $position) use ($view, $id, $members): Html {
				[$groups, $items, $fields] = $view->counts();

				$event = new GroupRemovalRendering($position, $id, $members, $groups, $items, $fields);
				$this->events->dispatch($event);

				return $view->place($event);
			};

			$start = $at(GroupRemovalRendering::OUTPUT_START);

			$at(GroupRemovalRendering::PRE_DEL_FIELDSET);
			$view->numberGroup('remove_group');

			$at(GroupRemovalRendering::PRE_MOVE_TO_GROUP);
			$view->numberItem('move_item');
			$view->numberField('move_field');
			$view->show('targets', $this->options($this->groups->moveTargets($id)));

			$at(GroupRemovalRendering::PRE_DEL_FIELDSET_END);
			$at(GroupRemovalRendering::DEL_FIELDSET_END);

			$body = $this->templates->render(self::REMOVE_TEMPLATE, $view->variables());

			$end = $at(GroupRemovalRendering::END);

			return array('main' => (new Html($start->html.$body.$end->html))->trim());
		});
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function page(array $common, array $strings): Response {
		$link = $this->urls->link('admin_groups');
		$action = new Html($link->html.'?action=foo');

		$view = new FormView(GroupsRendering::POSITIONS, array(
			'agr'		=> $strings,
			'action'	=> $action,
			'token'		=> $this->tokens->token($action->html),
		));

		return $this->pages->respond(new PageHead('admin-groups', $this->crumbs($common), section: 'users'), fn (): array => array('main' => $this->main($view, $link, $strings)));
	}

	/** @param array<string, Html> $strings */
	private function main(FormView $view, Html $link, array $strings): Html {
		$at = function (string $position) use ($view): Html {
			[$groups, $items, $fields] = $view->counts();

			$event = new GroupsRendering($position, $groups, $items, $fields);
			$this->events->dispatch($event);

			return $view->place($event);
		};

		$start = $at(GroupsRendering::MAIN_OUTPUT_START);

		$at(GroupsRendering::PRE_ADD_GROUP_FIELDSET);
		$view->numberGroup('add_group');

		$at(GroupsRendering::PRE_ADD_BASE_GROUP);
		$view->numberItem('base_item');
		$view->numberField('base_field');
		$view->show('baseGroups', $this->options($this->groups->baseGroups()));

		$at(GroupsRendering::PRE_ADD_GROUP_FIELDSET_END);
		$at(GroupsRendering::ADD_GROUP_FIELDSET_END);

		$view->restartGroupsAndItems();
		$at(GroupsRendering::PRE_DEFAULT_GROUP_FIELDSET);
		$view->numberGroup('default_group');

		$at(GroupsRendering::PRE_DEFAULT_GROUP);
		$view->numberItem('default_item');
		$view->numberField('default_field');
		$view->show('defaultCandidates', $this->options($this->groups->defaultCandidates()));

		$at(GroupsRendering::PRE_DEFAULT_GROUP_FIELDSET_END);
		$at(GroupsRendering::DEFAULT_GROUP_FIELDSET_END);

		$view->restartGroupsAndItems();

		$listed = array();
		foreach ($this->groups->all() as $group)
			$listed[] = $this->listedGroup($view, $group, $link, $strings);

		$view->show('groups', $listed);

		$body = $this->templates->render(self::TEMPLATE, $view->variables());

		$end = $at(GroupsRendering::END);

		return (new Html($start->html.$body.$end->html))->trim();
	}

	/**
	 * @param array<string, Html> $strings
	 * @return array<string, mixed> what the template shows of the group
	 */
	private function listedGroup(FormView $view, ListedGroupInterface $group, Html $link, array $strings): array {
		$default = $group->id() === $this->defaultGroupId();

		$options = array('edit' => Html::format('<span class="first-item"><a href="%s?edit_group=%s">%s</a></span>', $link, $group->id(), self::string($strings, 'Edit group'))->html);

		if ($group->id() <= GroupInterface::GUESTS)
			$options['remove'] = Html::format('<span>%s</span>', self::string($strings, 'Cannot remove group'))->html;
		else if ($default)
			$options['remove'] = Html::format('<span>%s</span>', self::string($strings, 'Cannot remove default'))->html;
		else
			$options['remove'] = Html::format('<span><a href="%s?del_group=%s&amp;csrf_token=%s">%s</a></span>', $link, $group->id(), $this->tokens->token('del_group'.$group->id().$this->visitor->id()), self::string($strings, 'Remove group'))->html;

		[$groups, $items, $fields] = $view->counts();
		$pre = new GroupRowAssembling(GroupRowAssembling::PRE_OUTPUT, $group, $default, $options, $groups, $items, $fields);
		$this->events->dispatch($pre);

		$listed = array('title' => $group->title(), 'default' => $default, 'preOutput' => $view->placeRow($pre));

		$options = array();
		foreach ($pre->names() as $name)
			$options[] = (string) $pre->entry($name);

		$listed['options'] = new Html(implode(' ', $options));
		$listed['item'] = $view->numberItem('group_item');

		[$groups, $items, $fields] = $view->counts();
		$post = new GroupRowAssembling(GroupRowAssembling::POST_OUTPUT, $group, $default, array(), $groups, $items, $fields);
		$this->events->dispatch($post);

		$listed['postOutput'] = $view->placeRow($post);

		return $listed;
	}

	/**
	 * @param list<ListedGroupInterface> $groups
	 * @return list<array{id: int, title: string, default: bool}> the groups as options of a list, the default group chosen
	 */
	private function options(array $groups): array {
		$default = $this->defaultGroupId();

		return array_map(static fn (ListedGroupInterface $group): array => array('id' => $group->id(), 'title' => $group->title(), 'default' => $group->id() === $default), $groups);
	}

	/** @param array<string, Html> $strings */
	private function label(array $strings, string $label, ?string $help): Html {
		return $help !== null ? Html::format('%s %s', self::string($strings, $label), self::string($strings, $help)) : self::string($strings, $label);
	}

	private function defaultGroupId(): int {
		return (int) $this->settings->value('o_default_user_group');
	}

	/**
	 * @param array<string, Html> $common
	 * @return list<Crumb>
	 */
	private function crumbs(array $common): array {
		return array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($common, 'Forum administration')->html, $this->urls->link('admin_index')),
			new Crumb(self::string($common, 'Users')->html, $this->urls->link('admin_users')),
			new Crumb(self::string($common, 'Groups')->html, $this->urls->link('admin_groups')),
		);
	}

	private function badRequest(Request $request): Response {
		return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
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
