<?php
/**
 * userlist.php as a module, built with no forum: who may see it, what it reads
 * from the directory and when, its head, the numbers of its form's fields as
 * observers add some, and the table as observers change its cells.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Userlist\Api\Data\MemberSearchInterface;
use PunBB\Module\Userlist\Api\MemberDirectoryInterface;
use PunBB\Module\Userlist\Controller\UserListController;
use PunBB\Module\Userlist\Event\MemberRowAssembling;
use PunBB\Module\Userlist\Event\MemberRowStarting;
use PunBB\Module\Userlist\Event\MemberTableAssembling;
use PunBB\Module\Userlist\Event\UserListRendering;
use PunBB\Module\Userlist\Event\UserListRequested;
use PunBB\Module\Userlist\Model\Group;
use PunBB\Module\Userlist\Model\Member;

require_once __DIR__.'/PageFakes.php';

final class FakeMemberDirectory implements MemberDirectoryInterface {
	/** @var list<Member> */
	public array $members = array();

	public int $total = 0;

	/** @var list<string> */
	public array $asked = array();

	public ?MemberSearchInterface $search = null;

	public function __construct(private array &$log) {}

	public function count(MemberSearchInterface $search): int {
		$this->log[] = 'count';
		$this->search = $search;

		return $this->total;
	}

	public function find(MemberSearchInterface $search, int $offset, int $limit): array {
		$this->log[] = 'find '.$offset.' '.$limit;

		return $this->members;
	}

	public function groups(): array {
		$this->log[] = 'groups';

		return array(new Group(1, 'Administrators'), new Group(4, 'Mods <&>'));
	}
}

class UserListControllerTest extends TestCase {
	private PageKit $kit;

	private FakeMemberDirectory $directory;

	/** @var list<string> */
	private array $log = array();

	protected function setUp(): void {
		$this->kit = new PageKit(array(UserListRequested::class, UserListRendering::class, MemberTableAssembling::class, MemberRowStarting::class, MemberRowAssembling::class, MessageShowing::class, MessageRendering::class));
		$this->kit->language->real = array('userlist');
		$this->kit->chromes->log = &$this->log;
		$this->directory = new FakeMemberDirectory($this->log);

		$this->kit->events->observe(UserListRendering::class, function (UserListRendering $event): void { $this->log[] = $event->position(); });
	}

	private function list(array $query = array()): string {
		$controller = new UserListController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->directory,
			$this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter);

		return $controller->handle(new Request('GET', '/', 'userlist.php', $query))->body;
	}

	private function members(int $count): void {
		$this->directory->total = $count;
		for ($id = 2; $id < $count + 2; $id++)
			$this->directory->members[] = new Member($id, 'user<'.$id.'>', '', $id * 10, 1000 + $id, 3, null);
	}

	public function testAVisitorWhoMayNotSeeTheMembersGetsAMessage(): void {
		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard);
		$this->assertStringContainsString('<p>[No permission]</p>', $this->list());

		$this->kit->visitor->permissions = array();
		$this->assertStringContainsString('<p>[No view]</p>', $this->list());
		$this->assertSame(array(), array_diff($this->log, array('open')));
	}

	public function testTheDirectoryIsReadWhereThePageReachesIt(): void {
		$this->members(2);
		$this->list(array('show_group' => '-2', 'p' => '1'));

		$this->assertSame(array('count', 'open', 'main_output_start', 'search_fieldset_start', 'pre_username', 'pre_group_select', 'search_new_group_option', 'groups',
			'pre_sort_by', 'new_sort_by_option', 'pre_sort_order_fieldset', 'pre_sort_order', 'pre_sort_order_fieldset_end', 'pre_search_fieldset_end', 'search_fieldset_end',
			'find 0 50', 'results_pre_header', 'end'), $this->log);
		$this->assertSame(-1, $this->directory->search?->groupId());
	}

	public function testTheHeadOfASecondPageLinksItsNeighboursAndShowsItsNumber(): void {
		$this->kit->language->real[] = 'common';
		$this->members(1);
		$this->directory->total = 120;
		$this->list(array('p' => '2', 'username' => 'a b*', 'sort_dir' => 'desc'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame('userlist', $head->id);
		$this->assertTrue($head->indexable);
		$this->assertSame(2, $head->page);
		$this->assertSame('(Page 2 of 3)', $head->pageCount?->html);
		$this->assertSame(array('last', 'next', 'prev', 'first'), array_keys($head->navigation));
		$this->assertSame('<link rel="last" href="/users_browse/-1/username/DESC/a+b%2A~page=3" title="Page 3" />', $head->navigation['last']->html);
		$this->assertSame('<link rel="first" href="/users_browse/-1/username/DESC/a+b%2A?a=1&amp;b=2" title="Page 1" />', $head->navigation['first']->html);
		$this->assertSame('<p class="paging"><span class="pages">Pages</span> [pages 2 of 3 at users_browse]</p>', $head->pagePost['paging']->html);
		$this->assertSame(array('Board & Co', 'User list'), array($head->crumbs[0]->text, $head->crumbs[1]->text));
	}

	public function testASinglePageHasNoNeighboursAndNoPageCount(): void {
		$this->members(1);
		$this->list();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array(), $head->navigation);
		$this->assertNull($head->pageCount);
		$this->assertSame(1, $head->page);
	}

	public function testTheFormNumbersOnFromWhereObserversLeaveItsCounts(): void {
		$this->kit->events->observe(UserListRendering::class, function (UserListRendering $event): void {
			if ($event->position() !== UserListRendering::PRE_SORT_BY)
				return;

			$event->append('<div class="sf-set set'.($event->itemCount() + 1).'"><input id="fld'.($event->fieldCount() + 1).'" /></div>'."\n");
			$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
		});

		$body = $this->list();

		$this->assertStringContainsString('<fieldset class="frm-group group1">', $body);
		$this->assertStringContainsString('<label for="fld2"><span>Show users from group</span></label>', $body);
		$this->assertStringContainsString("</div>\n<div class=\"sf-set set3\"><input id=\"fld3\" /></div>\n\t\t\t\t<div class=\"sf-set set4\">", $body);
		$this->assertStringContainsString('<select id="fld4" name="sort_by">', $body);
		$this->assertStringContainsString('<fieldset class="mf-set set5">', $body);
		$this->assertStringContainsString('<input type="radio" id="fld6" name="sort_dir" value="DESC" />', $body);
	}

	public function testAVisitorWhoMayNotSearchUsernamesGetsNoUsernameField(): void {
		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard, GroupPermission::ViewUsers);

		$body = $this->list(array('username' => 'admin'));

		$this->assertStringNotContainsString('name="username"', $body);
		$this->assertStringContainsString('<div class="sf-set set1">', $body);
		$this->assertSame('', $this->directory->search?->username());
	}

	public function testTheFormShowsTheSearchEscaped(): void {
		$body = $this->list(array('username' => '"x"<', 'show_group' => '4', 'sort_by' => 'registered', 'sort_dir' => 'DESC'));

		$this->assertStringContainsString('<p class="options"><span class="first-item"><a href="/users?a=1&amp;b=2">Perform new user search</a></span></p>', $body);
		$this->assertStringContainsString('name="username" value="&quot;x&quot;&lt;"', $body);
		$this->assertStringContainsString("<option value=\"-1\">All users</option>\n\t\t\t\t\t\t<option value=\"1\">Administrators</option>\n\t\t\t\t\t\t<option value=\"4\" selected=\"selected\">Mods &lt;&amp;&gt;</option>\n", $body);
		$this->assertStringContainsString('<option value="registered" selected="selected">', $body);
		$this->assertStringNotContainsString('value="num_posts"', $body);
	}

	public function testTheTableIsBuiltFromTheCellsObserversLeave(): void {
		$this->kit->visitor->moderating = true;
		$this->members(2);

		$this->kit->events->observe(MemberTableAssembling::class, function (MemberTableAssembling $event): void {
			$event->remove('title');
			$event->append('<p>table</p>');
		});
		$this->kit->events->observe(MemberRowStarting::class, fn (MemberRowStarting $event) => $event->append('<!--start '.$event->member()->id().'-->'));
		$this->kit->events->observe(MemberRowAssembling::class, function (MemberRowAssembling $event): void {
			$event->remove('title');
			$event->set('extra', '<td>#'.$event->number().'</td>');
			$event->append('<!--row-->');
		});

		$body = $this->list();

		$this->assertStringContainsString("</form>\n<p>table</p>\t\t<div class=\"ct-group\">", $body);
		$this->assertStringContainsString("<tr>\n\t\t\t\t\t\t<th class=\"tc0\" scope=\"col\">Username</th>\n\t\t\t\t\t\t<th class=\"tc2\" scope=\"col\">Posts</th>\n\t\t\t\t\t\t<th class=\"tc3\" scope=\"col\">Registration date</th>\n\t\t\t\t\t</tr>", $body);
		$this->assertStringContainsString("<tbody>\n<!--start 2--><!--row-->\t\t\t\t<tr class=\"odd row1\">\n\t\t\t\t\t<td class=\"tc0\"><a href=\"/user/2?a=1&amp;b=2\">user&lt;2&gt;</a></td>\n\t\t\t\t\t\t<td class=\"tc2\">20</td>\n\t\t\t\t\t\t<td class=\"tc3\"><time>1002</time></td>\n\t\t\t\t\t\t<td>#1</td>\n\t\t\t\t</tr>\n", $body);
		$this->assertStringContainsString("<!--start 3--><!--row-->\t\t\t\t<tr class=\"even\">", $body);
		$this->assertStringContainsString('<h2 class="hn"><span>Users: 1-2/2 in 1</span></h2>', $body);
	}

	public function testAnEmptyListSaysSoAndReachesNoTablePosition(): void {
		$body = $this->list();

		$this->assertStringContainsString("</form>\n\t\t<div class=\"ct-box\">\n\t\t\t<p><strong>No users were found matching your criteria</strong></p>", $body);
		$this->assertNotContains('results_pre_header', $this->log);
		$this->assertStringContainsString('<h2 class="hn"><span>Users</span></h2>', $body);
		$this->assertStringNotContainsString('class="options"', $body);
	}
}
