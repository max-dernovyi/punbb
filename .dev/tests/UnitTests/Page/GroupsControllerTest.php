<?php
/**
 * admin/groups.php as a module, built with no forum: who may see it, the list
 * with the forms adding a group and choosing the default one, the form adding
 * or editing a group for each kind of group, saving it, choosing the default,
 * and removing a group with and without members.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Groups\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Groups\Api\Data\GroupInterface;
use PunBB\Module\Groups\Api\Data\GroupMembersInterface;
use PunBB\Module\Groups\Api\GroupsInterface;
use PunBB\Module\Groups\Controller\GroupsController;
use PunBB\Module\Groups\Event\DefaultGroupStep;
use PunBB\Module\Groups\Event\GroupChangeStep;
use PunBB\Module\Groups\Event\GroupFormRendering;
use PunBB\Module\Groups\Event\GroupRemovalRendering;
use PunBB\Module\Groups\Event\GroupRemovalStep;
use PunBB\Module\Groups\Event\GroupRowAssembling;
use PunBB\Module\Groups\Event\GroupsRendering;
use PunBB\Module\Groups\Event\GroupsRequested;
use PunBB\Module\Groups\Model\ForumPermissions;
use PunBB\Module\Groups\Model\Group;
use PunBB\Module\Groups\Model\GroupMembers;
use PunBB\Module\Groups\Model\ListedGroup;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\ConfirmFormRendering;
use PunBB\Module\Message\Event\ConfirmFormRequested;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Site\Cache\ConfigCacheInterface;
use PunBB\Module\Site\Cache\QuickjumpCacheInterface;
use PunBB\Module\Site\Moderation\ModeratorListsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;

require_once __DIR__.'/PageFakes.php';

final class FakeGroups implements GroupsInterface, ModeratorListsInterface {
	/** @var array<int, Group> by title */
	public array $groups = array();

	/** @var array<int, GroupMembers> group id => its members */
	public array $members = array();

	/** @var array<int, list<ForumPermissions>> group id => the permissions the forums store for it */
	public array $permissions = array();

	/** @var list<string> */
	public array $log = array();

	/** @return list<ListedGroup> */
	private function listed(callable $keep): array {
		$listed = array();
		foreach ($this->groups as $group)
			if ($keep($group))
				$listed[] = new ListedGroup($group->id(), $group->title());

		return $listed;
	}

	public function all(): array {
		return $this->listed(static fn (): bool => true);
	}

	public function baseGroups(): array {
		return $this->listed(static fn (Group $group): bool => $group->id() > GroupInterface::GUESTS);
	}

	public function defaultCandidates(): array {
		return $this->listed(static fn (Group $group): bool => $group->id() > GroupInterface::GUESTS && !$group->allows(GroupPermission::Moderate->value));
	}

	public function moveTargets(int $id): array {
		return $this->listed(static fn (Group $group): bool => $group->id() !== GroupInterface::GUESTS && $group->id() !== $id);
	}

	public function baseGroup(int $id): ?GroupInterface {
		return $this->groups[$id] ?? null;
	}

	public function group(int $id): ?GroupInterface {
		return $this->groups[$id] ?? null;
	}

	public function titleTaken(string $title, ?int $exceptId): bool {
		foreach ($this->groups as $group)
			if ($group->title() === $title && $group->id() !== $exceptId)
				return true;

		return false;
	}

	public function add(GroupInterface ...$groups): void {
		foreach ($groups as $group)
			$this->log[] = 'add '.self::described($group);
	}

	public function lastAddedId(): int {
		return 9;
	}

	public function update(GroupInterface ...$groups): void {
		foreach ($groups as $group)
			$this->log[] = 'update '.$group->id().' '.self::described($group);
	}

	public function forumPermissions(int $groupId): array {
		return $this->permissions[$groupId] ?? array();
	}

	public function addForumPermissions(int $groupId, ForumPermissionsInterface ...$permissions): void {
		foreach ($permissions as $permission)
			$this->log[] = 'add permissions of '.$groupId.' in '.$permission->forumId().': '.(int) $permission->readForum().(int) $permission->postReplies().(int) $permission->postTopics();
	}

	public function isDefaultCandidate(int $id): bool {
		return isset($this->groups[$id]) && !$this->groups[$id]->allows(GroupPermission::Moderate->value);
	}

	public function makeDefault(int ...$ids): void {
		$this->log[] = 'default '.implode(',', $ids);
	}

	public function members(int $id): ?GroupMembersInterface {
		return $this->members[$id] ?? null;
	}

	public function moveMembers(int $toId, int ...$fromIds): void {
		$this->log[] = 'move '.implode(',', $fromIds).' to '.$toId;
	}

	public function remove(int ...$ids): void {
		$this->log[] = 'remove '.implode(',', $ids);
	}

	public function removeForumPermissions(int ...$groupIds): void {
		$this->log[] = 'remove permissions of '.implode(',', $groupIds);
	}

	public function clean(): void {
		$this->log[] = 'moderators';
	}

	public static function described(GroupInterface $group): string {
		$allowed = array();
		foreach (GroupPermission::cases() as $permission)
			if ($group->allows($permission->value))
				$allowed[] = $permission->name;

		return $group->title().' ('.var_export($group->userTitle(), true).') '.implode(',', $allowed).' '.$group->postFlood().'/'.$group->searchFlood().'/'.$group->emailFlood();
	}
}

class GroupsControllerTest extends TestCase {
	private PageKit $kit;

	private FakeGroups $groups;

	protected function setUp(): void {
		$this->kit = new PageKit(array(GroupsRequested::class, GroupChangeStep::class, DefaultGroupStep::class, GroupRemovalStep::class, GroupsRendering::class, GroupRowAssembling::class,
			GroupFormRendering::class, GroupRemovalRendering::class, MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class,
			ConfirmFormRequested::class, ConfirmFormRendering::class));
		$this->kit->language->real = array('admin_common', 'admin_groups', 'common');
		$this->kit->settings->values['o_redirect_delay'] = '0';
		$this->kit->settings->values['o_default_user_group'] = '3';
		$this->kit->visitor->administrator = true;

		$all = GroupPermission::cases();
		$members = array(GroupPermission::ReadBoard, GroupPermission::ViewUsers, GroupPermission::PostReplies, GroupPermission::PostTopics, GroupPermission::EditPosts, GroupPermission::Search, GroupPermission::SendEmail);

		$this->groups = new FakeGroups();
		$this->groups->groups = array(
			1 => new Group(1, 'Administrators', 'Administrator', $all, 0, 0, 0),
			5 => new Group(5, 'Banned <b>', null, array(), 30, 30, 60),
			2 => new Group(2, 'Guest', null, array(GroupPermission::ReadBoard, GroupPermission::Search), 60, 30, 0),
			3 => new Group(3, 'Members', null, $members, 60, 30, 60),
			4 => new Group(4, 'Moderators "mod"', 'Moderator', $all, 0, 0, 0),
		);
		$this->groups->members = array(4 => new GroupMembers('Moderators "mod"', 2));
		$this->groups->permissions = array(3 => array(new ForumPermissions(1, true, false, false), new ForumPermissions(2, false, false, false)));
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 */
	private function page(array $query = array(), array $post = array()): string {
		$log = &$this->groups->log;
		$quickjump = new class($log) implements QuickjumpCacheInterface {
			/** @param list<string> $log */
			public function __construct(private array &$log) {}

			public function rebuild(): void { $this->log[] = 'quickjump'; }

			public function clear(): void { $this->log[] = 'quickjump cleared'; }
		};
		$config = new class($log) implements ConfigCacheInterface {
			/** @param list<string> $log */
			public function __construct(private array &$log) {}

			public function rebuild(): void { $this->log[] = 'config'; }
		};

		$confirmations = new ConfirmPage($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->redirects(), $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->visitor, $this->kit->tokens);
		$controller = new GroupsController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(), $confirmations,
			$this->groups, $quickjump, $config, $this->groups, $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->tokens, $this->kit->flash);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'admin/groups.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testOnlyAnAdministratorGetsThePage(): void {
		$this->kit->visitor->administrator = false;
		$this->kit->visitor->moderating = true;

		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('del_group' => '5', 'csrf_token' => $this->kit->tokens->token('del_group53'))));
		$this->assertSame(array(), $this->groups->log);
		$this->assertSame(array('GroupsRequested'), array_slice($this->kit->events->dispatched, 0, 1));
	}

	public function testTheListOffersToAddAGroupChooseTheDefaultAndChangeEachGroup(): void {
		$body = $this->page();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-groups', 'users', null), array($head->id, $head->section, $head->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Users', 'Groups'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$token = 'token-for-'.md5('/admin_groups?a=1&amp;b=2?action=foo');
		$this->assertStringStartsWith("200  [admin-groups]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Add new group (will inherit the permissions of the group it is based on)</span></h2>", $body);
		$this->assertSame(2, substr_count($body, 'action="/admin_groups?a=1&amp;b=2?action=foo">'."\n\t\t\t<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"".$token.'" />'));
		$this->assertStringContainsString("<select id=\"fld1\" name=\"base_group\">\n\t\t\t\t\t\t\t<option value=\"5\">Banned &lt;b&gt;</option>\n\t\t\t\t\t\t\t<option value=\"3\" selected=\"selected\">Members</option>\n\t\t\t\t\t\t\t<option value=\"4\">Moderators &quot;mod&quot;</option>\n\t\t\t\t\t\t</select>", $body);
		$this->assertStringContainsString('<input type="submit" name="add_group" value="Add new group " />', $body);
		$this->assertStringContainsString("</form>\n\t</div>\n\t<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Default group for new users", $body);
		$this->assertStringContainsString("<fieldset class=\"frm-group group1\">\n\t\t\t\t<legend class=\"group-legend\"><span>Set default group for new users</span></legend>", $body);
		$this->assertStringContainsString("<select id=\"fld2\" name=\"default_group\">\n\t\t\t\t\t\t\t<option value=\"5\">Banned &lt;b&gt;</option>\n\t\t\t\t\t\t\t<option value=\"3\" selected=\"selected\">Members</option>\n\t\t\t\t\t\t</select>", $body);

		$this->assertStringContainsString("<div class=\"ct-group\">\n\t\t\t<div class=\"ct-set set1\">\n\t\t\t\t<div class=\"ct-box\">\n\t\t\t\t\t<h3 class=\"ct-legend hn\"><span>Administrators </span></h3>\n\t\t\t\t\t<p class=\"options\"><span class=\"first-item\"><a href=\"/admin_groups?a=1&amp;b=2?edit_group=1\">Edit this group</a></span> <span>This group cannot be removed.</span></p>", $body);
		$this->assertStringContainsString('<h3 class="ct-legend hn"><span>Banned &lt;b&gt; </span></h3>'."\n\t\t\t\t\t".'<p class="options"><span class="first-item"><a href="/admin_groups?a=1&amp;b=2?edit_group=5">Edit this group</a></span> <span><a href="/admin_groups?a=1&amp;b=2?del_group=5&amp;csrf_token='.$this->kit->tokens->token('del_group53').'">Remove this group</a></span></p>', $body);
		$this->assertStringContainsString('<span>Members (default)</span></h3>'."\n\t\t\t\t\t".'<p class="options"><span class="first-item"><a href="/admin_groups?a=1&amp;b=2?edit_group=3">Edit this group</a></span> <span>To remove this group you must assign a new default group.</span></p>', $body);
		$this->assertStringContainsString('<div class="ct-set set5">', $body);
		$this->assertStringEndsWith("</div>\n\t\t\t</div>\n\t\t</div>\n\t</div>", $body);
	}

	public function testObserversAddFieldsAndChangeAGroupsOptions(): void {
		$seen = array();
		$this->kit->events->observe(GroupsRendering::class, function (GroupsRendering $event) use (&$seen): void {
			if ($event->position() === GroupsRendering::PRE_ADD_BASE_GROUP)
			{
				$event->append('<input id="fld'.($event->fieldCount() + 1).'" />');
				$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
			}

			if ($event->position() === GroupsRendering::PRE_DEFAULT_GROUP || $event->position() === GroupsRendering::END)
				$seen[] = $event->position().' at '.$event->groupCount().'/'.$event->itemCount().'/'.$event->fieldCount();
		});
		$this->kit->events->observe(GroupRowAssembling::class, function (GroupRowAssembling $event) use (&$seen): void {
			if ($event->position() === GroupRowAssembling::PRE_OUTPUT && $event->group()->id() === 4)
			{
				$event->set('probe', '<span>probe</span>');
				$event->remove('edit');
				$event->append('<!-- before '.$event->group()->title().' -->');
			}

			if ($event->position() === GroupRowAssembling::POST_OUTPUT)
				$seen[] = $event->group()->id().($event->isDefault() ? ' default' : '').' at '.$event->itemCount();
		});

		$body = $this->page();

		$this->assertStringContainsString('<input id="fld1" />'."\t\t\t\t<div class=\"sf-set set2\">", $body);
		$this->assertStringContainsString('<select id="fld2" name="base_group">', $body);
		$this->assertStringContainsString("<!-- before Moderators \"mod\" -->\t\t\t<div class=\"ct-set set5\">", $body);
		$this->assertStringContainsString('<p class="options"><span><a href="/admin_groups?a=1&amp;b=2?del_group=4&amp;csrf_token='.$this->kit->tokens->token('del_group43').'">Remove this group</a></span> <span>probe</span></p>', $body);
		$this->assertSame(array('pre_default_group at 1/0/2', '1 at 1', '5 at 2', '2 at 3', '3 default at 4', '4 at 5', 'end at 0/5/3'), $seen);
	}

	public function testTheFormEditsAMembersGroupWithoutModeration(): void {
		$body = $this->page(array('edit_group' => '3'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-groups', 'users', 'form'), array($head->id, $head->section, $head->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Users', 'Groups', 'Edit existing group'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$this->assertStringContainsString("<form class=\"frm-form\" method=\"post\" accept-charset=\"utf-8\" action=\"/admin_groups?a=1&amp;b=2\">\n\t\t\t<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/admin_groups?a=1&amp;b=2')."\" />\n\t\t\t\t<input type=\"hidden\" name=\"mode\" value=\"edit\" />\n\t\t\t\t<input type=\"hidden\" name=\"group_id\" value=\"3\" />\n\t\t\t</div>", $body);
		$this->assertStringContainsString('name="req_title" size="25" maxlength="50" value="Members" required />', $body);
		$this->assertStringContainsString("<h3 class=\"hn\"><span>Group permissions</span></h3>\n\t\t\t</div>\n\t\t\t\t<div class=\"ct-box\">", $body);
		$this->assertStringContainsString("<p class=\"warn\">This is the default group for new users and therefore cannot be assigned moderator privileges.</p>\n\t\t\t\t</div>\n\t\t\t\t<fieldset class=\"frm-group group1\">", $body);
		$this->assertStringNotContainsString('name="moderator"', $body);
		$this->assertStringContainsString("<fieldset class=\"mf-set set1\">\n\t\t\t\t\t\t<legend><span>User permissions</span></legend>", $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld3" name="read_board" value="1" checked="checked" /></span>'."\n\t\t\t\t\t\t\t\t".'<label for="fld3">Allow users to view the board. This setting applies', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld8" name="delete_posts" value="1" />', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld13" name="send_email" value="1" checked="checked" />', $body);
		$this->assertStringContainsString('<input type="text" id="fld14" name="post_flood" size="5" maxlength="4" value="60" />', $body);
		$this->assertStringContainsString("</div>\n\t\t\t\t\t\t\t\t<div class=\"sf-set set3\">", $body);
		$this->assertStringContainsString('<input type="text" id="fld16" name="email_flood" size="5" maxlength="4" value="60" />', $body);
		$this->assertStringEndsWith("<input type=\"submit\" name=\"add_edit_group\" value=\" Update group \" /></span>\n\t\t\t</div>\n\t\t</form>\n\t</div>", $body);
	}

	public function testTheFormAddsAGroupBasedOnTheModerators(): void {
		$body = $this->page(array('action' => 'foo'), array('add_group' => '1', 'base_group' => '4'));

		$this->assertSame('Add new group (will inherit the permissions of the group it is based on)', $this->kit->chromes->opened[0]->crumbs[4]->text);
		$this->assertStringContainsString("<input type=\"hidden\" name=\"mode\" value=\"add\" />\n\t\t\t\t<input type=\"hidden\" name=\"base_group\" value=\"4\" />\n\t\t\t</div>", $body);
		$this->assertStringContainsString('name="req_title" size="25" maxlength="50" value="" required />', $body);
		$this->assertStringContainsString('name="user_title" size="25" maxlength="50" value="Moderator" />', $body);
		$this->assertStringNotContainsString('This is the default group', $body);
		$this->assertStringContainsString("<fieldset class=\"mf-set set1\">\n\t\t\t\t\t\t<legend><span>Moderator permissions</span></legend>", $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld3" name="moderator" value="1" checked="checked" /></span>'."\n\t\t\t\t\t\t\t\t".'<label for="fld3">Allow users moderator privileges. In order for a user', $body);
		$this->assertStringContainsString("</fieldset>\n\t\t\t\t\t<fieldset class=\"mf-set set2\">\n\t\t\t\t\t\t<legend><span>User permissions</span></legend>", $body);
		$this->assertStringContainsString('<input type="text" id="fld21" name="email_flood"', $body);
	}

	public function testTheGuestsFormLacksModerationAndWhatAGuestCannotDo(): void {
		$positions = array();
		$this->kit->events->observe(GroupFormRendering::class, function (GroupFormRendering $event) use (&$positions): void {
			$positions[] = $event->position();
			if ($event->position() === GroupFormRendering::PRE_EMAIL_INTERVAL)
				$event->append("<!-- email interval at {$event->fieldCount()} -->\n");
		});

		$body = $this->page(array('edit_group' => '2'));

		$this->assertStringNotContainsString('name="moderator"', $body);
		$this->assertStringNotContainsString('This is the default group', $body);
		foreach (array('edit_posts', 'delete_posts', 'delete_topics', 'set_title', 'send_email', 'email_flood') as $field)
			$this->assertStringNotContainsString('name="'.$field.'"', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld7" name="search" value="1" checked="checked" />', $body);
		$this->assertStringContainsString("name=\"search_flood\" size=\"5\" maxlength=\"4\" value=\"30\" /></span>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n<!-- email interval at 10 -->\n\t\t\t</fieldset>", $body);
		$this->assertContains(GroupFormRendering::PRE_ALLOW_EDIT_POSTS_CHECKBOX, $positions);
		$this->assertContains(GroupFormRendering::PRE_ALLOW_SEND_EMAIL_CHECKBOX, $positions);
		foreach (array(GroupFormRendering::PRE_MOD_PERMISSIONS_FIELDSET_END, GroupFormRendering::PRE_ALLOW_DELETE_POSTS_CHECKBOX, GroupFormRendering::PRE_EMAIL_INTERVAL_FIELD) as $position)
			$this->assertNotContains($position, $positions);
	}

	public function testTheAdministratorsFormHasTheTitlesOnly(): void {
		$positions = array();
		$this->kit->events->observe(GroupFormRendering::class, function (GroupFormRendering $event) use (&$positions): void {
			$positions[] = $event->position();
		});

		$body = $this->page(array('edit_group' => '1'));

		$this->assertStringContainsString("</fieldset>\n\t\t\t<div class=\"frm-buttons\">", $body);
		$this->assertStringNotContainsString('Group permissions', $body);
		$this->assertSame(array_slice(GroupFormRendering::POSITIONS, 0, 6), array_slice($positions, 0, 6));
		$this->assertSame(array(GroupFormRendering::END), array_slice($positions, 6));
	}

	public function testObserversNumberOnAcrossTheFormsParts(): void {
		$seen = array();
		$this->kit->events->observe(GroupFormRendering::class, function (GroupFormRendering $event) use (&$seen): void {
			if ($event->position() === GroupFormRendering::PRE_ALLOW_SEND_EMAIL_CHECKBOX)
			{
				$event->append("\t\t\t\t\t\t\t<div class=\"mf-item\"><input id=\"fld".($event->fieldCount() + 1)."\" name=\"probe\" /></div>\n");
				$event->count($event->groupCount(), $event->itemCount(), $event->fieldCount() + 1);
			}

			if (in_array($event->position(), array(GroupFormRendering::PRE_PERMISSIONS_FIELDSET, GroupFormRendering::PRE_FLOOD_FIELDSET, GroupFormRendering::PRE_EMAIL_INTERVAL_FIELD, GroupFormRendering::END), true))
				$seen[] = $event->position().' at '.$event->groupCount().'/'.$event->itemCount().'/'.$event->fieldCount();

			if ($event->position() === GroupFormRendering::PRE_EMAIL_INTERVAL_FIELD)
				$event->append('<!-- field -->');
		});

		$body = $this->page(array('edit_group' => '5'));

		$this->assertStringContainsString("<div class=\"mf-item\"><input id=\"fld18\" name=\"probe\" /></div>\n\t\t\t\t\t\t\t<div class=\"mf-item\">\n\t\t\t\t\t\t\t\t<span class=\"fld-input\"><input type=\"checkbox\" id=\"fld19\" name=\"send_email\"", $body);
		$this->assertStringContainsString("</div>\n\t\t\t\t<!-- field -->\t\t\t\t<div class=\"sf-set set3\">", $body);
		$this->assertSame(array('pre_permissions_fieldset at 0/0/2', 'pre_flood_fieldset at 0/0/19', 'pre_email_interval_field at 1/2/21', 'end at 1/3/22'), $seen);
	}

	public function testAnEditedGroupIsSavedAndItsModeratorsCleaned(): void {
		$steps = array();
		$this->kit->events->observe(GroupChangeStep::class, function (GroupChangeStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.($event->group() !== null ? FakeGroups::described($event->group()) : '');
		});

		$response = $this->page(array(), array('add_edit_group' => '1', 'mode' => 'edit', 'group_id' => '5', 'req_title' => ' Banned ', 'user_title' => ' ',
			'mod_edit_users' => '1', 'read_board' => '1', 'search' => 'yes', 'post_flood' => '12s', 'search_flood' => array('1')));

		$this->assertStringStartsWith('302 /admin_groups?a=1&b=2 [redirect]', $response);
		$this->assertSame(array('update 5 Banned (NULL) ReadBoard 12/1/0', 'moderators', 'quickjump'), $this->groups->log);
		$this->assertSame(array('validated Banned (NULL) ReadBoard 12/1/0', 'editing Banned (NULL) ReadBoard 12/1/0', 'saved Banned (NULL) ReadBoard 12/1/0'), $steps);
		$this->assertSame(array('Group edited.'), $this->kit->flash->info);
	}

	public function testTheAdministratorsAreAllowedEverythingButModeration(): void {
		$this->page(array(), array('add_edit_group' => '1', 'mode' => 'edit', 'group_id' => '1', 'req_title' => 'Administrators', 'user_title' => 'Admin', 'moderator' => '1', 'mod_ban_users' => '1'));

		$this->assertSame(array('update 1 Administrators (\'Admin\') BanUsers,ReadBoard,ViewUsers,PostReplies,PostTopics,EditPosts,DeletePosts,DeleteTopics,SetTitle,Search,SearchUsers,SendEmail 0/0/0', 'moderators', 'quickjump'), $this->groups->log);
	}

	public function testAGroupIsAddedWithTheForumsPermissionsOfItsBase(): void {
		$this->assertStringStartsWith('302 ', $this->page(array(), array('add_edit_group' => '1', 'mode' => 'add', 'base_group' => '3', 'req_title' => 'Club', 'moderator' => '1', 'mod_rename_users' => '1', 'post_topics' => '1')));

		$this->assertSame(array('add Club (NULL) Moderate,RenameUsers,PostTopics 0/0/0', 'add permissions of 9 in 1: 100', 'add permissions of 9 in 2: 000', 'quickjump'), $this->groups->log);
		$this->assertSame(array('Group added.'), $this->kit->flash->info);
	}

	public function testASaveIsRefusedWithoutATitleOrWithOneTakenOrModeratingTheDefault(): void {
		$this->assertStringContainsString('<p>You must enter a group title.</p>', $this->page(array(), array('add_edit_group' => '1', 'mode' => 'add', 'req_title' => ' ')));
		$this->assertStringContainsString('<p>There is already a group with the title <strong>"Members &amp; &lt;co&gt;"</strong>.</p>', $this->withTitledGroup(fn (): string => $this->page(array(), array('add_edit_group' => '1', 'mode' => 'add', 'req_title' => 'Members & <co>'))));
		$this->assertStringContainsString('There is already a group with the title', $this->page(array(), array('add_edit_group' => '1', 'mode' => 'edit', 'group_id' => '5', 'req_title' => 'Members')));
		$this->assertStringContainsString('<p>This is the default group for new users and therefore cannot be assigned moderator privileges.</p>', $this->page(array(), array('add_edit_group' => '1', 'mode' => 'edit', 'group_id' => '3', 'req_title' => 'Members', 'moderator' => '1')));

		$this->assertSame(array(), $this->groups->log);
	}

	public function testTheFormOfAnUnknownGroupIsABadRequest(): void {
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('edit_group' => '0')));
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('edit_group' => '9')));
		$this->assertStringContainsString('<p>Bad request.', $this->page(array(), array('add_group' => '1', 'base_group' => '9')));
	}

	public function testTheDefaultGroupIsOneThatNeitherAdministersNorModerates(): void {
		$steps = array();
		$this->kit->events->observe(DefaultGroupStep::class, function (DefaultGroupStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->groupId();
		});

		$this->assertStringStartsWith('302 /admin_groups?a=1&b=2 [redirect]', $this->page(array('action' => 'foo'), array('set_default_group' => '1', 'default_group' => '5')));
		$this->assertSame(array('default 5', 'config'), $this->groups->log);
		$this->assertSame(array('Default group set.'), $this->kit->flash->info);

		foreach (array('1', '2', '4', '9') as $refused)
			$this->assertStringContainsString('<p>Bad request.', $this->page(array(), array('set_default_group' => '1', 'default_group' => $refused)));

		$this->assertSame(array('default 5', 'config'), $this->groups->log);
		$this->assertSame(array('setting 5', 'set 5', 'setting 1', 'setting 2', 'setting 4', 'setting 9'), $steps);
	}

	public function testAGroupWithMembersAsksWhereTheyMove(): void {
		$this->kit->events->observe(GroupRemovalRendering::class, function (GroupRemovalRendering $event): void {
			if ($event->position() === GroupRemovalRendering::PRE_MOVE_TO_GROUP)
				$event->append('<!-- moving '.$event->members()->count().' of '.$event->groupId().' -->');
		});

		$body = $this->page(array('del_group' => '4'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-groups', 'users', 'remove'), array($head->id, $head->section, $head->view));
		$this->assertSame('Remove this group', $head->crumbs[4]->text);
		$this->assertStringStartsWith("200  [admin-groups]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Remove \"Moderators &quot;mod&quot;\" group which contains 2 members</span></h2>", $body);
		$this->assertStringContainsString('action="/admin_groups?a=1&amp;b=2?del_group=4">'."\n\t\t\t<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/admin_groups?a=1&amp;b=2?del_group=4').'" />', $body);
		$this->assertStringContainsString("<fieldset class=\"frm-group set1\">\n\t\t\t\t<legend class=\"group-legend\"><span>Remove group</span></legend>\n<!-- moving 2 of 4 -->\t\t\t\t<div class=\"sf-set set1\">", $body);
		$this->assertStringContainsString("<select id=\"fld1\" name=\"move_to_group\">\n\t\t\t\t\t\t\t<option value=\"1\">Administrators</option>\n\t\t\t\t\t\t\t<option value=\"5\">Banned &lt;b&gt;</option>\n\t\t\t\t\t\t\t<option value=\"3\" selected=\"selected\">Members</option>\n\n\t\t\t\t\t\t</select>", $body);
		$this->assertSame(array(), $this->groups->log);
	}

	public function testAGroupIsRemovedWithItsMembersMoved(): void {
		$steps = array();
		$this->kit->events->observe(GroupRemovalStep::class, function (GroupRemovalStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->groupId().' to '.var_export($event->movedTo(), true);
		});

		$this->assertStringStartsWith('302 /admin_groups?a=1&b=2 [redirect]', $this->page(array('del_group' => '4'), array('del_group' => '1', 'move_to_group' => '5', 'csrf_token' => 'checked by the gate')));
		$this->assertSame(array('move 4 to 5', 'remove 4', 'remove permissions of 4', 'moderators', 'quickjump'), $this->groups->log);
		$this->assertSame(array('selected 4 to NULL', 'removing 4 to 5', 'removed 4 to 5'), $steps);
		$this->assertSame(array('Group removed.'), $this->kit->flash->info);
	}

	public function testAGroupWithoutMembersIsRemovedByItsLinkAndAnUntokenedLinkIsConfirmedFirst(): void {
		$this->assertStringContainsString('[dialogue]', $this->page(array('del_group' => '5')));
		$this->assertStringContainsString('[dialogue]', $this->page(array('del_group' => '5', 'csrf_token' => $this->kit->tokens->token('del_group5'))));
		$this->assertSame(array(), $this->groups->log);

		$this->assertStringStartsWith('302 /admin_groups?a=1&b=2 [redirect]', $this->page(array('del_group' => '5', 'csrf_token' => $this->kit->tokens->token('del_group53'))));
		$this->assertSame(array('remove 5', 'remove permissions of 5', 'moderators', 'quickjump'), $this->groups->log);

		$this->assertStringStartsWith('302 ', $this->page(array('del_group' => '5'), array('csrf_token' => 'checked by the gate')));
		$this->assertCount(8, $this->groups->log);
	}

	public function testTheGuestsTheAdministratorsAndTheDefaultGroupAreNotRemoved(): void {
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('del_group' => '2', 'csrf_token' => $this->kit->tokens->token('del_group23'))));
		$this->assertStringContainsString('<p>The default group cannot be removed.', $this->page(array('del_group' => '3', 'csrf_token' => $this->kit->tokens->token('del_group33'))));
		$this->assertStringStartsWith('302 /admin_groups?a=1&b=2 [redirect]', $this->page(array('del_group' => '5'), array('del_group' => '1', 'del_group_cancel' => '1')));

		$this->assertSame(array(), $this->groups->log);
	}

	private function withTitledGroup(Closure $page): string {
		$this->groups->groups[6] = new Group(6, 'Members & <co>', null, array(), 0, 0, 0);

		try {
			return $page();
		}
		finally {
			unset($this->groups->groups[6]);
		}
	}
}
