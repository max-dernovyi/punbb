<?php
/**
 * admin/forums.php as a module, built with no forum: who may see it, the form
 * adding a forum and the list numbered as observers add fields, the
 * confirmation a deletion asks for, the form editing a forum with each group's
 * permissions, and each change.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Forums\Api\Data\ForumInterface;
use PunBB\Module\Forums\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Forums\Api\Data\ForumPositionInterface;
use PunBB\Module\Forums\Api\ForumsInterface;
use PunBB\Module\Forums\Controller\ForumsController;
use PunBB\Module\Forums\Event\ForumChangeStep;
use PunBB\Module\Forums\Event\ForumDeletionRendering;
use PunBB\Module\Forums\Event\ForumFormRendering;
use PunBB\Module\Forums\Event\ForumsRendering;
use PunBB\Module\Forums\Event\ForumsRequested;
use PunBB\Module\Forums\Event\GroupPermissionRendering;
use PunBB\Module\Forums\Event\ListedForumRendering;
use PunBB\Module\Forums\Event\PermissionsComparing;
use PunBB\Module\Forums\Model\Category;
use PunBB\Module\Forums\Model\Forum;
use PunBB\Module\Forums\Model\ForumPermissions;
use PunBB\Module\Forums\Model\ForumPosition;
use PunBB\Module\Forums\Model\GroupDefaults;
use PunBB\Module\Forums\Model\GroupPermissions;
use PunBB\Module\Forums\Model\ListedForum;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Site\Cache\QuickjumpCacheInterface;
use PunBB\Module\Site\Removal\ForumContentsInterface;

require_once __DIR__.'/PageFakes.php';

final class FakeForums implements ForumsInterface, ForumContentsInterface, QuickjumpCacheInterface {
	/** @var list<ListedForum> */
	public array $listed = array();

	/** @var list<Category> */
	public array $categories = array();

	/** @var array<int, Forum> */
	public array $forums = array();

	/** @var list<GroupPermissions> */
	public array $groups = array();

	/** @var array<int, true> group id => whether the forum stores its permissions */
	public array $stored = array();

	/** @var list<string> */
	public array $log = array();

	public function all(): array {
		return $this->listed;
	}

	public function categories(): array {
		return $this->categories;
	}

	public function assignableCategories(): array {
		return $this->categories;
	}

	public function categoryExists(int $id): bool {
		foreach ($this->categories as $category)
			if ($category->id() === $id)
				return true;

		return false;
	}

	public function add(ForumInterface ...$forums): void {
		foreach ($forums as $forum)
			$this->log[] = 'add '.$forum->name().' in '.$forum->categoryId().'@'.$forum->position();
	}

	public function find(int $id): ?ForumInterface {
		return $this->forums[$id] ?? null;
	}

	public function name(int $id): ?string {
		return isset($this->forums[$id]) ? $this->forums[$id]->name() : null;
	}

	public function update(ForumInterface ...$forums): void {
		foreach ($forums as $forum)
			$this->log[] = 'update '.$forum->id().' '.$forum->name().' in '.$forum->categoryId().' sort '.$forum->sortBy().' desc '.var_export($forum->description(), true).' to '.var_export($forum->redirectUrl(), true);
	}

	public function positions(): array {
		return array_map(static fn (ListedForum $forum): ForumPosition => new ForumPosition($forum->id(), $forum->position()), $this->listed);
	}

	public function reposition(ForumPositionInterface ...$positions): void {
		foreach ($positions as $position)
			$this->log[] = 'move '.$position->forumId().'@'.$position->position();
	}

	public function remove(int ...$ids): void {
		$this->log[] = 'remove '.implode(',', $ids);
	}

	public function removePermissions(int ...$forumIds): void {
		$this->log[] = 'remove permissions '.implode(',', $forumIds);
	}

	public function removeSubscriptions(int ...$forumIds): void {
		$this->log[] = 'remove subscriptions '.implode(',', $forumIds);
	}

	public function groupDefaults(): array {
		return array_map(static fn (GroupPermissions $group): GroupDefaults => new GroupDefaults($group->groupId(), $group->readsBoard(), $group->postsReplies(), $group->postsTopics()), $this->groups);
	}

	public function groupPermissions(int $forumId): array {
		return $this->groups;
	}

	public function permissionsStored(int $forumId, int $groupId): bool {
		return isset($this->stored[$groupId]);
	}

	public function updatePermissions(int $forumId, ForumPermissionsInterface ...$permissions): void {
		foreach ($permissions as $permission)
			$this->log[] = 'update permissions '.$permission->groupId().' in '.$forumId.': '.self::bits($permission);
	}

	public function addPermissions(int $forumId, ForumPermissionsInterface ...$permissions): void {
		foreach ($permissions as $permission)
			$this->log[] = 'add permissions '.$permission->groupId().' in '.$forumId.': '.self::bits($permission);
	}

	public function removeGroupPermissions(int $forumId, int ...$groupIds): void {
		$this->log[] = 'remove permissions of '.implode(',', $groupIds).' in '.$forumId;
	}

	public function revertPermissions(int ...$forumIds): void {
		$this->log[] = 'revert '.implode(',', $forumIds);
	}

	public function empty(int $forumId): void {
		$this->log[] = 'empty '.$forumId;
	}

	public function removeOrphans(): void {
		$this->log[] = 'orphans';
	}

	public function rebuild(): void {
		$this->log[] = 'quickjump';
	}

	public function clear(): void {
		$this->log[] = 'quickjump cleared';
	}

	public static function bits(ForumPermissionsInterface $permissions): string {
		return (int) $permissions->readForum().(int) $permissions->postReplies().(int) $permissions->postTopics();
	}
}

class ForumsControllerTest extends TestCase {
	private PageKit $kit;

	private FakeForums $forums;

	protected function setUp(): void {
		$this->kit = new PageKit(array(ForumsRequested::class, ForumChangeStep::class, ForumsRendering::class, ListedForumRendering::class, ForumFormRendering::class, GroupPermissionRendering::class,
			PermissionsComparing::class, ForumDeletionRendering::class, MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('admin_common', 'admin_forums', 'admin_categories', 'common');
		$this->kit->settings->values['o_redirect_delay'] = '0';
		$this->kit->visitor->administrator = true;

		$this->forums = new FakeForums();
		$this->forums->categories = array(new Category(2, 'News & <views>'), new Category(1, 'Talk'));
		$this->forums->listed = array(new ListedForum(2, 'News & <views>', 3, 'Notices <b>', 1), new ListedForum(1, 'Talk', 2, 'Elsewhere', 1), new ListedForum(1, 'Talk', 1, 'General', 2));
		$this->forums->forums = array(
			1 => new Forum(1, 'General "chat"', 'All <b>talk</b>', null, 1, 1, 0, 4),
			2 => new Forum(2, 'Elsewhere', null, 'http://example.com/?a=1&b=2', 0, 1),
		);
		$this->forums->groups = array(
			new GroupPermissions(2, 'Guest', true, false, false, null, true, null),
			new GroupPermissions(3, 'Members', true, true, true, false, false, null),
			new GroupPermissions(4, 'Banned <x>', false, false, false, null, null, null),
		);
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 */
	private function page(array $query = array(), array $post = array()): string {
		$controller = new ForumsController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(),
			$this->forums, $this->forums, $this->forums, $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->tokens, $this->kit->flash);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'admin/forums.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testOnlyAnAdministratorGetsThePage(): void {
		$this->kit->visitor->administrator = false;
		$this->kit->visitor->moderating = true;

		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('del_forum' => '1'), array('del_forum_comply' => '1')));
		$this->assertSame(array(), $this->forums->log);
		$this->assertSame(array('ForumsRequested'), array_slice($this->kit->events->dispatched, 0, 1));
	}

	public function testTheFormAddsAForumAndTheListMovesThemByCategory(): void {
		$body = $this->page();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-forums', 'start', null), array($head->id, $head->section, $head->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Start', 'Forums'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$this->assertStringStartsWith("200  [admin-forums]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Add a new forum to the selected category at the specified position</span></h2>", $body);
		$this->assertStringContainsString('action="/admin_forums?a=1&amp;b=2?action=adddel">'."\n\t\t\t<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/admin_forums?a=1&amp;b=2?action=adddel').'" />', $body);
		$this->assertStringContainsString("<select id=\"fld3\" name=\"add_to_cat\">\n\t\t\t\t\t\t\t<option value=\"2\">News &amp; &lt;views&gt;</option>\n\t\t\t\t\t\t\t<option value=\"1\">Talk</option>\n\t\t\t\t\t\t</select>", $body);
		$this->assertStringContainsString('<input type="submit" name="add_forum" value=" Add forum " />', $body);
		$this->assertStringContainsString("</form>\n\t</div>\n\n\t<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Edit, delete or change the position of forums</span></h2>", $body);
		$this->assertStringContainsString('<input type="hidden" name="csrf_token" value="token-for-'.md5('/admin_forums?a=1&amp;b=2?action=edit').'" />'."\n\t\t\t</div>\n\n\t\t\t<div class=\"content-head\">\n\t\t\t\t<h3 class=\"hn\"><span>Forums in category: News &amp; &lt;views&gt;</span></h3>", $body);
		$this->assertStringContainsString("<fieldset id=\"forum3\" class=\"mf-set set1 mf-head\">\n\t\t\t\t\t<legend><span><a href=\"/admin_forums?a=1&amp;b=2?edit_forum=3\">Edit</a> or <a href=\"/admin_forums?a=1&amp;b=2?del_forum=3\">Delete</a></span></legend>", $body);
		$this->assertStringContainsString('<span class="fld-input">Notices &lt;b&gt;</span>', $body);
		$this->assertStringContainsString("</fieldset>\n\t\t\t</div>\n\t\t\t<div class=\"content-head\">\n\t\t\t\t<h3 class=\"hn\"><span>Forums in category: Talk</span></h3>\n\t\t\t</div>\n\t\t\t<div class=\"frm-group frm-hdgroup group1\">\n\n\t\t\t\t<fieldset id=\"forum2\" class=\"mf-set set1 mf-head\">", $body);
		$this->assertStringContainsString('<fieldset id="forum1" class="mf-set set2 mf-extra">', $body);
		$this->assertStringContainsString('<input type="number" id="fld6" name="position[1]" size="3" maxlength="3" value="2" />', $body);
		$this->assertStringEndsWith("<input type=\"submit\" name=\"update_positions\" value=\"Update positions\" /></span>\n\t\t\t</div>\n\t\t</form>\n\t</div>", $body);
	}

	public function testWithoutForumsOnlyTheFormAddingOneIsThere(): void {
		$this->forums->listed = array();

		$body = $this->page();

		$this->assertSame(1, substr_count($body, '<form'));
		$this->assertStringEndsWith("</form>\n\t</div>", $body);
	}

	public function testObserversAddFieldsAndTheFormsNumberOn(): void {
		$seen = array();
		$this->kit->events->observe(ForumsRendering::class, function (ForumsRendering $event) use (&$seen): void {
			if ($event->position() === ForumsRendering::PRE_NEW_FORUM_CAT)
			{
				$event->append('<div class="set'.($event->itemCount() + 1).'"><input id="fld'.($event->fieldCount() + 1).'" /></div>');
				$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
			}

			if ($event->position() === ForumsRendering::MAIN_OUTPUT_START)
				$event->append('<!-- start -->');

			if ($event->position() === ForumsRendering::END)
				$seen[] = 'end at '.$event->groupCount().'/'.$event->itemCount().'/'.$event->fieldCount();
		});
		$this->kit->events->observe(ListedForumRendering::class, function (ListedForumRendering $event) use (&$seen): void {
			if ($event->position() === ListedForumRendering::PRE_EDIT_CUR_FORUM_FIELDSET)
				$seen[] = $event->forum()->name().' at '.$event->groupCount().'/'.$event->itemCount().'/'.$event->fieldCount();

			if ($event->position() === ListedForumRendering::PRE_EDIT_CUR_FORUM_POSITION && $event->forum()->id() === 2)
			{
				$event->append('<input id="fld'.($event->fieldCount() + 1).'" name="probe" />');
				$event->count($event->groupCount(), $event->itemCount(), $event->fieldCount() + 1);
			}
		});

		$body = $this->page();

		$this->assertStringStartsWith("200  [admin-forums]<!-- start -->\t<div class=\"main-subhead\">", $body);
		$this->assertStringContainsString('<div class="set3"><input id="fld3" /></div>'."\t\t\t\t<div class=\"sf-set set4\">", $body);
		$this->assertStringContainsString('<select id="fld4" name="add_to_cat">', $body);
		$this->assertStringContainsString('<input id="fld6" name="probe" />'."\t\t\t\t\t\t<div class=\"mf-field\">\n\t\t\t\t\t\t\t<label for=\"fld7\">", $body);
		$this->assertSame(array('Notices <b> at 1/0/4', 'Elsewhere at 1/0/5', 'General at 1/1/7', 'end at 1/2/8'), $seen);
	}

	public function testAForumIsAddedAndTheVisitorSentBack(): void {
		$steps = array();
		$this->kit->events->observe(ForumChangeStep::class, function (ForumChangeStep $event) use (&$steps): void {
			$forum = $event->forum();
			$steps[] = $event->step().' '.$forum?->name().' in '.$forum?->categoryId().'@'.$forum?->position().' '.implode(',', $this->forums->log);
		});

		$this->assertStringStartsWith('302 /admin_forums?a=1&b=2 [redirect]', $this->page(array('action' => 'adddel'), array('add_forum' => '1', 'forum_name' => ' Games ', 'position' => '5x', 'add_to_cat' => '2')));
		$this->assertSame(array('add Games in 2@5', 'quickjump'), $this->forums->log);
		$this->assertSame(array('adding Games in 2@5 ', 'added Games in 2@5 add Games in 2@5,quickjump'), $steps);
		$this->assertSame(array('Forum added.'), $this->kit->flash->info);
	}

	public function testAForumWithoutANameOrACategoryIsRefused(): void {
		$this->assertStringContainsString('<p>You must enter a forum name.</p>', $this->page(array(), array('add_forum' => '1', 'forum_name' => array('Games'), 'add_to_cat' => '2')));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array(), array('add_forum' => '1', 'forum_name' => 'Games', 'add_to_cat' => '0')));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array(), array('add_forum' => '1', 'forum_name' => 'Games', 'add_to_cat' => '9')));

		$this->assertSame(array(), $this->forums->log);
	}

	public function testADeletionIsConfirmedFirst(): void {
		$seen = array();
		$this->kit->events->observe(ForumChangeStep::class, function (ForumChangeStep $event) use (&$seen): void {
			$seen[] = $event->step().' '.$event->forum()?->id().' '.($event->confirmed() ? 'confirmed' : 'unconfirmed');
		});
		$this->kit->events->observe(ForumDeletionRendering::class, function (ForumDeletionRendering $event): void {
			$event->append('<!-- '.$event->position().' '.$event->forum()->name().' -->');
		});

		$body = $this->page(array('del_forum' => '1'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-forums', 'start', 'delete'), array($head->id, $head->section, $head->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Start', 'Forums', 'Delete forum'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));
		$this->assertStringStartsWith("200  [admin-forums]<!-- output_start General \"chat\" -->\t<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>You are deleting the forum \"General &quot;chat&quot;\"</span></h2>", $body);
		$this->assertStringContainsString('action="/admin_forums?a=1&amp;b=2?del_forum=1">'."\n\t\t\t<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/admin_forums?a=1&amp;b=2?del_forum=1').'" />', $body);
		$this->assertStringEndsWith("</form>\n\t</div>\n<!-- end General \"chat\" -->", $body);
		$this->assertSame(array('deleting 1 unconfirmed'), $seen);
		$this->assertSame(array(), $this->forums->log);
	}

	public function testAConfirmedDeletionEmptiesTheForumFirst(): void {
		$this->assertStringStartsWith('302 /admin_forums?a=1&b=2 [redirect]', $this->page(array('del_forum' => '2'), array('del_forum_comply' => '1')));
		$this->assertSame(array('empty 2', 'orphans', 'remove 2', 'remove permissions 2', 'remove subscriptions 2', 'quickjump'), $this->forums->log);
		$this->assertSame(array('Forum deleted.'), $this->kit->flash->info);
	}

	public function testADeletionOfNothingOrCancelledOrOfAnUnknownForumDeletesNothing(): void {
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('del_forum' => '0'), array('del_forum_comply' => '1')));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('del_forum' => '9')));
		$this->assertStringStartsWith('302 /admin_forums?a=1&b=2 [redirect]', $this->page(array('del_forum' => '1'), array('del_forum_comply' => '1', 'del_forum_cancel' => '1')));

		$this->assertSame(array(), $this->forums->log);
	}

	public function testOnlyTheMovedForumsAreRepositioned(): void {
		$steps = array();
		$this->kit->events->observe(ForumChangeStep::class, function (ForumChangeStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.implode(' ', array_map(static fn (ForumPositionInterface $position): string => $position->forumId().'@'.$position->position(), $event->positions()));
		});

		$this->page(array('action' => 'edit'), array('update_positions' => '1', 'position' => array('3' => '1', '2' => ' 4', '1' => '2', '12' => '9')));

		$this->assertSame(array('move 2@4', 'quickjump'), $this->forums->log);
		$this->assertSame(array('reordering 3@1 2@4 1@2 12@9', 'reordered 3@1 2@4 1@2 12@9'), $steps);
		$this->assertSame(array('Forums updated.'), $this->kit->flash->info);
	}

	public function testANegativePositionOrNoPositionsStoreNothing(): void {
		$this->assertStringContainsString('<p>Position must be a positive integer value</p>', $this->page(array(), array('update_positions' => '1', 'position' => array('3' => '5', '1' => '-1'))));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array(), array('update_positions' => '1', 'position' => '1')));

		$this->assertSame(array(), $this->forums->log);
	}

	public function testTheFormEditsAForumsDetailsAndEachGroupsPermissions(): void {
		$body = $this->page(array('edit_forum' => '1'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-forums', 'start', 'edit'), array($head->id, $head->section, $head->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Start', 'Forums', 'Edit forum'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$this->assertStringStartsWith("200  [admin-forums]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Edit forum: General &quot;chat&quot;</span></h2>", $body);
		$this->assertStringContainsString('<form method="post" class="frm-form" accept-charset="utf-8" action="/admin_forums?a=1&amp;b=2?edit_forum=1">', $body);
		$this->assertStringContainsString('name="forum_name" size="35" maxlength="80" value="General &quot;chat&quot;" required />', $body);
		$this->assertStringContainsString('<textarea id="fld2" name="forum_desc" rows="3" cols="50">All &lt;b&gt;talk&lt;/b&gt;</textarea>', $body);
		$this->assertStringContainsString("<select id=\"fld3\" name=\"cat_id\">\n\t\t\t\t\t\t\t\t<option value=\"2\">News &amp; &lt;views&gt;</option>\n\t\t\t\t\t\t\t\t<option value=\"1\" selected=\"selected\">Talk</option>\n\t\t\t\t\t\t</select>", $body);
		$this->assertStringContainsString("<option value=\"0\">Last post</option>\n\t\t\t\t\t\t\t<option value=\"1\" selected=\"selected\">Topic start</option>\n\t\t\t\t\t\t</select>", $body);
		$this->assertStringContainsString('<input type="url" id="fld5" name="redirect_url" size="45" maxlength="100" value="Only available in empty forums" disabled="disabled" />', $body);
		$this->assertStringContainsString("</fieldset>\n\t\t\t<div class=\"content-head\">\n\t\t\t\t<h3 class=\"hn\"><span>Edit forum permissions</span></h3>", $body);
		$this->assertStringContainsString("<ul>\n\t\t\t\t\t<li><span>The \"Read forum\" permission", $body);
		$this->assertStringContainsString('unless suffixed "(S)"</span></li>'."\n\t\t\t\t\t<li><span>Forum Administrators always have full permissions which cannot be restricted.</span></li>\n\t\t\t\t</ul>", $body);
		$this->assertStringNotContainsString('This is a redirect forum.', $body);
		$this->assertStringContainsString('<a href="/admin_groups?a=1&amp;b=2">User groups</a>', $body);

		$this->assertStringContainsString("<fieldset class=\"frm-group group1\">\n\t\t\t\t<legend class=\"group-legend\"><strong>Forum group permissions", $body);
		$this->assertStringContainsString("<fieldset class=\"mf-set set1\">\n\t\t\t\t\t<legend><span>Guest</span></legend>", $body);
		$this->assertStringContainsString('<input type="hidden" name="post_replies_old[2]" value="1" />'."\n\t\t\t\t\t\t\t".'<span class="fld-input"><input type="checkbox" id="fld7" name="post_replies_new[2]" value="1" checked="checked" /></span>'."\n\t\t\t\t\t\t\t".'<label for="fld7" class="warn">Post replies (S)</label>', $body);
		$this->assertStringContainsString('<label for="fld8">Post topics </label>', $body);
		$this->assertStringContainsString('<input type="hidden" name="read_forum_old[3]" value="0" />'."\n\t\t\t\t\t\t\t".'<span class="fld-input"><input type="checkbox" id="fld9" name="read_forum_new[3]" value="1" /></span>'."\n\t\t\t\t\t\t\t".'<label for="fld9" class="warn">Read&#160;forum (S)</label>', $body);
		$this->assertStringContainsString('<input type="hidden" name="post_topics_old[3]" value="1" />', $body);
		$this->assertStringContainsString("<legend><span>Banned &lt;x&gt;</span></legend>", $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld12" name="read_forum_new[4]" value="1" checked="checked" disabled="disabled" /></span>'."\n\t\t\t\t\t\t\t".'<label for="fld12">Read&#160;forum </label>', $body);
		$this->assertStringEndsWith("<input type=\"submit\" name=\"revert_perms\" value=\"Default permissions\" formnovalidate /></span>\n\t\t\t</div>\n\t\t</form>\n\t</div>", $body);
	}

	public function testARedirectForumsFormSaysSoAndLocksPosting(): void {
		$body = $this->page(array('edit_forum' => '2'));

		$this->assertStringContainsString("<ul>\n\t\t\t\t\t<li><span>This is a redirect forum. Only the \"Read forum\" permission is editable.</span></li>\n\t\t\t\t\t<li><span>The \"Read forum\"", $body);
		$this->assertStringContainsString('<input type="text" id="fld5" name="redirect_url" size="35" maxlength="100" value="http://example.com/?a=1&amp;b=2" />', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld7" name="post_replies_new[2]" value="1" checked="checked" disabled="disabled" />', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld6" name="read_forum_new[2]" value="1" checked="checked" />', $body);
	}

	public function testObserversChangeTheLinesAndAddFieldsInTheGroupsFieldsets(): void {
		$seen = array();
		$this->kit->events->observe(ForumFormRendering::class, function (ForumFormRendering $event) use (&$seen): void {
			if ($event->position() === ForumFormRendering::OUTPUT_START)
			{
				$event->remove('admins');
				$event->set('probe', '<li>probe</li>');
			}

			if ($event->position() === ForumFormRendering::PRE_FORUM_CAT)
			{
				$event->append('<div class="set'.($event->itemCount() + 1).'"><select id="fld'.($event->fieldCount() + 1).'"></select></div>');
				$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
			}

			if ($event->position() === ForumFormRendering::MODIFY_SORT_BY)
				$event->append("\t\t\t\t\t\t\t<option value=\"2\">Probe sort</option>\n");

			if ($event->position() === ForumFormRendering::PRE_PERMISSIONS_PART || $event->position() === ForumFormRendering::END)
				$seen[] = $event->position().' at '.$event->groupCount().'/'.$event->itemCount().'/'.$event->fieldCount().' '.implode(',', $event->names());
		});
		$this->kit->events->observe(GroupPermissionRendering::class, function (GroupPermissionRendering $event) use (&$seen): void {
			if ($event->position() === GroupPermissionRendering::PRE_CUR_GROUP_PERMISSIONS_FIELDSET)
				$seen[] = $event->group()->groupTitle().' '.FakeForums::bits($event->shown()).' at '.$event->groupCount().'/'.$event->itemCount().'/'.$event->fieldCount();

			if ($event->position() === GroupPermissionRendering::POST_CUR_GROUP_POST_TOPICS_PERMISSION && $event->group()->groupId() === 3)
			{
				$event->append('<input id="fld'.($event->fieldCount() + 1).'" name="probe" />'."\n");
				$event->count($event->groupCount(), $event->itemCount(), $event->fieldCount() + 1);
			}
		});

		$body = $this->page(array('edit_forum' => '1'));

		$this->assertStringContainsString('<div class="set3"><select id="fld3"></select></div>'."\t\t\t\t<div class=\"sf-set set4\">", $body);
		$this->assertStringContainsString("<option value=\"1\" selected=\"selected\">Topic start</option>\n\t\t\t\t\t\t\t<option value=\"2\">Probe sort</option>\n\t\t\t\t\t\t</select>", $body);
		$this->assertStringContainsString('unless suffixed "(S)"</span></li>'."\n\t\t\t\t\t<li>probe</li>\n\t\t\t\t</ul>", $body);
		$this->assertStringContainsString("<input id=\"fld13\" name=\"probe\" />\n\t\t\t\t\t</div>", $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld14" name="read_forum_new[4]"', $body);
		$this->assertSame(array(
			'pre_permissions_part at 0/0/6 read,restore,groups,probe',
			'Guest 110 at 1/0/6',
			'Members 001 at 1/1/9',
			'Banned <x> 100 at 1/2/13',
			'end at 1/3/16 ',
		), $seen);
	}

	public function testTheDetailsAndTheChangedPermissionsAreSaved(): void {
		$this->forums->stored = array(3 => true);
		$steps = array();
		$this->kit->events->observe(ForumChangeStep::class, function (ForumChangeStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->forum()?->id().' '.$event->forum()?->name();
		});

		$response = $this->page(array('edit_forum' => '2'), array(
			'save'				=> '1',
			'forum_name'		=> ' Away ',
			'forum_desc'		=> "One\r\ntwo\rthree",
			'cat_id'			=> '2',
			'sort_by'			=> '1',
			'redirect_url'		=> ' http://example.org/ ',
			'read_forum_old'	=> array('2' => '1', '3' => '0', '4' => '1'),
			'post_replies_old'	=> array('2' => '1', '3' => '0', '4' => '0'),
			'post_topics_old'	=> array('2' => '0', '3' => '0', '4' => '0'),
			'read_forum_new'	=> array('2' => '1', '3' => '1', '4' => '1'),
			'post_replies_new'	=> array('2' => '1', '3' => '1', '4' => '1'),
			'post_topics_new'	=> array('3' => '1'),
		));

		$this->assertStringStartsWith('302 /admin_forums_forum/2?a=1&b=2 [redirect]', $response);
		$this->assertSame(array(
			'update 2 Away in 2 sort 1 desc \'One'."\n".'two'."\n".'three\' to \'http://example.org/\'',
			// Guest: shown as submitted, nothing to store
			// Members: the group's defaults, so the forum's own permissions go
			'remove permissions of 3 in 2',
			// Banned: the read permission stays as shown where the group cannot read the board, replying is new
			'update permissions 4 in 2: 110',
			'add permissions 4 in 2: 110',
			'quickjump',
		), $this->forums->log);
		$this->assertSame(array('selected 2 ', 'saving 2 Away', 'saved 2 Away'), $steps);
		$this->assertSame(array('Forum updated.'), $this->kit->flash->info);
	}

	public function testAStoredPermissionIsUpdatedAndNotAddedAgain(): void {
		$this->forums->stored = array(4 => true);

		$this->page(array('edit_forum' => '1'), array('save' => '1', 'forum_name' => 'General', 'cat_id' => '1', 'read_forum_old' => array('4' => '1'), 'post_replies_new' => array('4' => '1')));

		$this->assertSame(array('update 1 General in 1 sort 0 desc NULL to NULL', 'update permissions 4 in 1: 110', 'quickjump'), $this->forums->log);
	}

	public function testAnObserverChangesWhatIsCompared(): void {
		$compared = array();
		$this->kit->events->observe(PermissionsComparing::class, function (PermissionsComparing $event) use (&$compared): void {
			$compared[] = $event->forumId().' '.$event->group()->groupId().' '.FakeForums::bits($event->defaults()).' '.FakeForums::bits($event->shown()).' '.FakeForums::bits($event->submitted());

			if ($event->group()->groupId() === 2)
				$event->change($event->defaults(), $event->shown(), new ForumPermissions(2, false, false, false));
		});

		$this->page(array('edit_forum' => '1'), array('save' => '1', 'forum_name' => 'General', 'cat_id' => '1',
			'read_forum_old' => array('2' => '1', '3' => '1', '4' => '0'), 'read_forum_new' => array('2' => '1', '3' => '1')));

		$this->assertSame(array('1 2 100 100 100', '1 3 111 100 100', '1 4 000 000 000'), $compared);
		$this->assertSame(array('update 1 General in 1 sort 0 desc NULL to NULL', 'update permissions 2 in 1: 000', 'add permissions 2 in 1: 000', 'quickjump'), $this->forums->log);
	}

	public function testASaveWithoutANameOrACategoryStoresNothing(): void {
		$this->assertStringContainsString('<p>You must enter a forum name.</p>', $this->page(array('edit_forum' => '1'), array('save' => '1', 'forum_name' => ' ', 'cat_id' => '1')));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('edit_forum' => '1'), array('save' => '1', 'forum_name' => 'General', 'cat_id' => '0')));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('edit_forum' => '9'), array('save' => '1', 'forum_name' => 'General', 'cat_id' => '1')));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('edit_forum' => '-1')));

		$this->assertSame(array(), $this->forums->log);
	}

	public function testAForumsPermissionsAreRevertedToTheDefaults(): void {
		$this->assertStringStartsWith('302 /admin_forums?a=1&b=2?edit_forum=1 [redirect]', $this->page(array('edit_forum' => '1'), array('revert_perms' => '1')));
		$this->assertSame(array('revert 1', 'quickjump'), $this->forums->log);
		$this->assertSame(array('Permissions reverted to defaults.'), $this->kit->flash->info);
	}
}
