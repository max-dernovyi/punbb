<?php
/**
 * admin/users.php as a module, built with no forum: who may see it, the search
 * forms, the addresses a user posted from, the users who posted from an
 * address, a search's users a page at a time, and deleting, banning and moving
 * into another group the users selected.
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
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Site\Cache\BanCacheInterface;
use PunBB\Module\Site\Moderation\ModeratorListsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Users\Api\Data\FoundUserInterface;
use PunBB\Module\Users\Api\Data\UserBanInterface;
use PunBB\Module\Users\Api\Data\UserSearchInterface;
use PunBB\Module\Users\Api\UsersInterface;
use PunBB\Module\Users\Controller\UsersController;
use PunBB\Module\Users\Event\ActionFormRendering;
use PunBB\Module\Users\Event\BanUsersStep;
use PunBB\Module\Users\Event\ChangeGroupStep;
use PunBB\Module\Users\Event\DeleteUsersStep;
use PunBB\Module\Users\Event\ModerationButtonsAssembling;
use PunBB\Module\Users\Event\ResultRowAssembling;
use PunBB\Module\Users\Event\ResultsEnding;
use PunBB\Module\Users\Event\ResultsTableAssembling;
use PunBB\Module\Users\Event\SearchSelected;
use PunBB\Module\Users\Event\UsersActionRequested;
use PunBB\Module\Users\Event\UserSearchFormRendering;
use PunBB\Module\Users\Event\UsersRequested;
use PunBB\Module\Users\Model\AddressUse;
use PunBB\Module\Users\Model\BanTarget;
use PunBB\Module\Users\Model\FoundUser;
use PunBB\Module\Users\Model\ListedGroup;
use PunBB\Module\Users\Model\PostAddress;
use PunBB\Module\Users\Model\Poster;
use PunBB\Module\Site\Removal\UserRemovalInterface;

require_once __DIR__.'/PageFakes.php';

final class FakeUsers implements UsersInterface, UserRemovalInterface, BanCacheInterface, ModeratorListsInterface {
	/** @var array<int, list<AddressUse>> user id => the addresses they posted from */
	public array $addresses = array();

	/** @var array<string, list<Poster>> address => who posted from it */
	public array $posters = array();

	/** @var array<int, FoundUser> */
	public array $members = array();

	/** @var list<FoundUser> what every search finds */
	public array $found = array();

	/** @var list<int> the administrators */
	public array $administrators = array(2);

	/** @var list<PostAddress> */
	public array $postAddresses = array();

	/** @var array<int, ?bool> group id => whether it moderates */
	public array $moderating = array(1 => false, 3 => false, 4 => true);

	/** @var list<string> */
	public array $log = array();

	public ?UserSearchInterface $searched = null;

	public function addressesOf(int $userId): array {
		return $this->addresses[$userId] ?? array();
	}

	public function postersFrom(string $address): array {
		return $this->posters[$address] ?? array();
	}

	public function member(int $id): ?FoundUserInterface {
		return $this->members[$id] ?? null;
	}

	public function count(UserSearchInterface $search): int {
		$this->searched = $search;

		return count($this->found);
	}

	public function find(UserSearchInterface $search, int $offset, int $limit): array {
		$this->log[] = 'find from '.$offset.' at most '.$limit;

		return array_slice($this->found, $offset, $limit);
	}

	public function searchGroups(): array {
		return $this->groups();
	}

	public function includesAdministrators(int ...$ids): bool {
		return array_intersect($ids, $this->administrators) !== array();
	}

	public function postAddresses(int ...$ids): array {
		return array_values(array_filter($this->postAddresses, static fn (PostAddress $address): bool => in_array($address->userId(), $ids, true)));
	}

	public function banTargets(int ...$ids): array {
		return array_map(static fn (int $id): BanTarget => new BanTarget($id, 'user'.$id, 'user'.$id.'@example.com', '192.0.2.'.$id), array_values(array_filter($ids, static fn (int $id): bool => $id > 1)));
	}

	public function ban(UserBanInterface ...$bans): void {
		foreach ($bans as $ban)
			$this->log[] = 'ban '.$ban->userId().' '.$ban->username().' at '.$ban->ip().' '.$ban->email().' '.var_export($ban->message(), true).' until '.var_export($ban->expire(), true).' by '.$ban->creatorId();
	}

	public function groupModerates(int $id): ?bool {
		return $this->moderating[$id] ?? null;
	}

	public function moveToGroup(int $groupId, int ...$ids): void {
		$this->log[] = 'move '.implode(',', $ids).' to '.$groupId;
	}

	public function moveTargets(): array {
		return $this->groups();
	}

	public function remove(int $userId, bool $withPosts): void {
		$this->log[] = 'remove '.$userId.($withPosts ? ' with posts' : '');
	}

	public function rebuild(): void {
		$this->log[] = 'bans cache';
	}

	public function clean(): void {
		$this->log[] = 'moderators';
	}

	/** @return list<ListedGroup> */
	private function groups(): array {
		return array(new ListedGroup(1, 'Administrators'), new ListedGroup(3, 'Members <b>'), new ListedGroup(4, 'Moderators'));
	}
}

class UsersControllerTest extends TestCase {
	private PageKit $kit;

	private FakeUsers $users;

	protected function setUp(): void {
		$this->kit = new PageKit(array(UsersRequested::class, UsersActionRequested::class, SearchSelected::class, ResultsTableAssembling::class, ResultRowAssembling::class,
			ModerationButtonsAssembling::class, ResultsEnding::class, DeleteUsersStep::class, BanUsersStep::class, ChangeGroupStep::class, ActionFormRendering::class,
			UserSearchFormRendering::class, MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('admin_common', 'admin_users', 'admin_bans', 'misc', 'common');
		$this->kit->settings->values['o_redirect_delay'] = '0';
		$this->kit->settings->values['o_default_user_group'] = '3';
		$this->kit->visitor->administrator = true;
		$this->kit->visitor->moderating = true;
		$this->kit->visitor->id = 2;

		$this->users = new FakeUsers();
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 */
	private function page(array $query = array(), array $post = array()): string {
		$controller = new UsersController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(),
			$this->users, $this->users, $this->users, $this->users, $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter, $this->kit->tokens, $this->kit->flash);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'admin/users.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	/** @return list<string> */
	private function crumbs(): array {
		return array_map(static fn ($crumb): string => $crumb->text, $this->kit->chromes->opened[0]->crumbs);
	}

	public function testOnlyAnAdministratorOrAModeratorGetsThePage(): void {
		$this->kit->visitor->administrator = $this->kit->visitor->moderating = false;

		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('find_user' => '1')));
		$this->assertSame(array('UsersRequested'), array_slice($this->kit->events->dispatched, 0, 1));
		$this->assertNotContains('SearchSelected', $this->kit->events->dispatched);
	}

	public function testTheSearchFormsNumberTheirFieldsetsAndListTheGroups(): void {
		$body = $this->page();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-users', 'users', null), array($head->id, $head->section, $head->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Users', 'Searches'), $this->crumbs());
		$this->assertNotNull($head->crumbs[3]->link, 'the searches are the last crumb and a link');
		$this->assertSame('UsersActionRequested', $this->kit->events->dispatched[1]);

		$this->assertStringStartsWith("200  [admin-users]<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Find users</span></h2>", $body);
		$this->assertStringContainsString('<input type="hidden" name="csrf_token" value="token-for-'.md5('/admin_users?a=1&amp;b=2?action=find_user').'" />', $body);
		$this->assertStringContainsString("<fieldset class=\"frm-group group1\">\n\t\t\t\t<legend class=\"group-legend\"><strong>Personal details</strong></legend>\n\t\t\t\t<div class=\"sf-set set1\">\n\t\t\t\t\t<div class=\"sf-box text\">\n\t\t\t\t\t\t<label for=\"fld1\"><span>Username</span></label><br />\n\t\t\t\t\t\t<span class=\"fld-input\"><input type=\"text\" id=\"fld1\" name=\"form[username]\" size=\"35\" maxlength=\"25\" /></span>", $body);
		$this->assertStringContainsString("<fieldset class=\"frm-group group2\">\n\t\t\t\t<legend class=\"group-legend\"><strong>Contact details</strong></legend>\n\t\t\t\t<div class=\"sf-set set1\">", $body);
		$this->assertStringContainsString('<input type="text" id="fld10" name="form[icq]" size="12" maxlength="12" />', $body);
		$this->assertStringContainsString("<div class=\"sf-box frm-short text\">\n\t\t\t\t\t\t<label for=\"fld14\"><span>More posts than</span> <small>(Number of posts)</small></label><br />\n\t\t\t\t\t\t<span class=\"fld-input\"><input type=\"number\" id=\"fld14\" name=\"posts_greater\" size=\"5\" maxlength=\"8\" /></span>", $body);
		$this->assertStringContainsString('<label for="fld17"><span>Last post is before</span><small>[ yyyy-mm-dd hh:mm:ss ]</small></label>', $body);
		$this->assertStringContainsString("<fieldset class=\"frm-group group1\">\n\t\t\t\t<legend class=\"group-legend\"><strong>Search results</strong></legend>", $body);
		$this->assertStringContainsString("<option value=\"0\">Unverified users</option>\n\t\t\t\t\t\t\t<option value=\"1\">Administrators</option>\n\t\t\t\t\t\t\t<option value=\"3\">Members &lt;b&gt;</option>\n\t\t\t\t\t\t\t<option value=\"4\">Moderators</option>\n\t\t\t\t\t\t</select>", $body);
		$this->assertStringContainsString("</form>\n\t</div>\n\n\t<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Find a specific IP address in the post database</span></h2>", $body);
		$this->assertStringContainsString('<input type="text" id="fld23" name="show_users" size="18" maxlength="15" required />', $body);
		$this->assertStringEndsWith("<input type=\"submit\" value=\" Submit search \" /></span>\n\t\t\t</div>\n\t\t</form>\n\t</div>", $body);

		$this->kit->visitor->administrator = false;
		$this->kit->chromes->opened = array();
		$this->page();
		$this->assertSame(array('Board & Co', 'Administration', 'Searches'), $this->crumbs(), 'a moderator is not shown the users\' section');
	}

	public function testObserversAddFieldsTheFormNumbersOn(): void {
		$seen = array();
		$this->kit->events->observe(UserSearchFormRendering::class, function (UserSearchFormRendering $event) use (&$seen): void {
			if ($event->position() === UserSearchFormRendering::PRE_USERNAME)
			{
				$event->append('<input id="fld'.($event->fieldCount() + 1).'" />');
				$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
			}

			if (in_array($event->position(), array(UserSearchFormRendering::PRE_WEBSITE, UserSearchFormRendering::PRE_RESULTS_FIELDSET, UserSearchFormRendering::NEW_FILTER_GROUP_OPTION, UserSearchFormRendering::END), true))
				$seen[] = $event->position().' at '.$event->groupCount().'/'.$event->itemCount().'/'.$event->fieldCount();
		});

		$body = $this->page();

		$this->assertStringContainsString("<input id=\"fld1\" />\t\t\t\t<div class=\"sf-set set2\">\n\t\t\t\t\t<div class=\"sf-box text\">\n\t\t\t\t\t\t<label for=\"fld2\"><span>Username</span></label>", $body);
		$this->assertSame(array('pre_website at 2/1/8', 'pre_results_fieldset at 0/0/20', 'new_filter_group_option at 1/3/23', 'end at 1/1/24'), $seen);
	}

	public function testTheAddressesOfAUserAreListedWithTheirUse(): void {
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('ip_stats' => '0')));

		$this->users->addresses[3] = array(new AddressUse('192.0.2.3', 500, 2), new AddressUse('<b>', 100, 1));
		$this->kit->events->observe(ResultRowAssembling::class, function (ResultRowAssembling $event): void {
			if ($event->stage() === ResultRowAssembling::CELLS && $event->number() === 2)
			{
				$event->set('probe', '<td>probe '.$event->address()?->timesUsed().'</td>');
				$event->setStyle($event->style().' probed');
			}
		});

		$this->kit->chromes->opened = array();
		$body = $this->page(array('ip_stats' => '3'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-iresults', 'users', null), array($head->id, $head->section, $head->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Users', 'Searches', 'User search results'), $this->crumbs());
		$this->assertContains('SearchSelected', $this->kit->events->dispatched);

		$this->assertStringStartsWith("200  [admin-iresults]<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>IP addresses found [ 2 ]</span></h2>\n\t</div>\n\t<div class=\"main-content main-forum\">", $body);
		$this->assertStringContainsString("<tr>\n\t\t\t\t\t<th class=\"tc0\" scope=\"col\">IP address</th>\n\t\t\t\t<th class=\"tc1\" scope=\"col\">Last used</th>\n\t\t\t\t<th class=\"tc2\" scope=\"col\">Times found</th>\n\t\t\t\t<th class=\"tc3\" scope=\"col\">Action(s)</th>\n\t\t\t\t</tr>", $body);
		$this->assertStringContainsString("<tr class=\"odd row1\">\n\t\t\t\t\t<td class=\"tc0\"><a href=\"/get_host/192.0.2.3?a=1&amp;b=2\">192.0.2.3</a></td>\n\t\t\t\t<td class=\"tc1\"><time>500</time></td>\n\t\t\t\t<td class=\"tc2\">2</td>\n\t\t\t\t<td class=\"tc3\"><a href=\"/admin_users?a=1&amp;b=2?show_users=192.0.2.3\">Find more users for this IP</a></td>\n\t\t\t\t</tr>", $body);
		$this->assertStringContainsString("<tr class=\"even probed\">\n\t\t\t\t\t<td class=\"tc0\"><a href=\"/get_host/<b>?a=1&amp;b=2\">&lt;b&gt;</a></td>", $body);
		$this->assertStringContainsString("<td>probe 1</td>\n\t\t\t\t</tr>", $body);
		$this->assertStringEndsWith("<h2 class=\"hn\"><span>IP addresses found [ 2 ]</span></h2>\n\t</div>", $body);

		$empty = $this->page(array('ip_stats' => '4'));
		$this->assertStringContainsString("<tr class=\"odd row1\">\n\t\t\t\t\t<td class=\"tc0\">There are currently no posts by that user in the forum.</td>\n\t\t\t\t<td class=\"tc1\"> - </td>", $empty);
	}

	public function testThePostersFromAnAddressAreListedWithTheButtonsTheVisitorMayUse(): void {
		$this->assertStringContainsString('<p>The IP address you entered is not correctly formatted.</p>', $this->page(array('show_users' => 'localhost')));
		$this->assertStringContainsString('<p>The IP address you entered is not correctly formatted.</p>', $this->page(array('show_users' => array('192.0.2.3'))));

		$this->users->posters['192.0.2.3'] = array(new Poster(3, 'member'), new Poster(1, 'Visitor <i>'));
		$this->users->members[3] = new FoundUser(3, 'member', 'member@example.com', 'Veteran', 1234, 'watch <this>', 3, null);

		$this->kit->chromes->opened = array();
		$body = $this->page(array('show_users' => '192.0.2.3'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-uresults', 'users', 'show_users'), array($head->id, $head->section, $head->view));
		$this->assertStringContainsString("<p class=\"options\"><span class=\"select-all js_link\" data-check-form=\"aus-show-users-results-form\">Select all</span></p>\t\t<h2 class=\"hn\"><span>Users found [ 2 ]</span></h2>", $body);
		$this->assertStringContainsString("<form id=\"aus-show-users-results-form\" class=\"frm-form\" method=\"post\" accept-charset=\"utf-8\" action=\"/admin_users?a=1&amp;b=2?action=modify_users\">\n\t<div class=\"main-content main-frm\">", $body);
		$this->assertStringContainsString('<th class="tc4" scope="col">Select</th>', $body);
		$this->assertStringContainsString('<td class="tc0"><span><a href="/user/3?a=1&amp;b=2">member</a></span><span class="usermail"><a href="mailto:member@example.com">member@example.com</a></span><span class="usernote">Admin note watch &lt;this&gt;</span></td>', $body);
		$this->assertStringContainsString("<td class=\"tc1\">Veteran</td>\n\t\t\t\t<td class=\"tc2\">1&#160;234</td>\n\t\t\t\t<td class=\"tc3\"><span><a href=\"/admin_users?a=1&amp;b=2?ip_stats=3\">View IP stats</a></span> <span><a href=\"/search_user_posts/3?a=1&amp;b=2\">Show posts</a></span></td>\n\t\t\t\t<td class=\"tc4\"><input type=\"checkbox\" name=\"users[3]\" value=\"1\" /></td>", $body);
		$this->assertStringContainsString("<tr class=\"even\">\n\t\t\t\t\t<td class=\"tc0\">Visitor &lt;i&gt;</td>\n\t\t\t\t<td class=\"tc1\">Guest</td>\n\t\t\t\t<td class=\"tc2\"> - </td>", $body);
		$this->assertStringContainsString("</table>\n\t</div>\n\t<div class=\"main-options gen-content\">\n\t\t<p class=\"options\"><span class=\"submit first-item\"><input type=\"submit\" name=\"ban_users\" value=\"Ban\" /></span> <span class=\"submit\"><input type=\"submit\" name=\"delete_users\" value=\"Delete\" /></span> <span class=\"submit\"><input type=\"submit\" name=\"change_group\" value=\"Change group\" /></span></p>\n\t</div>\n\t</form>", $body);
		$this->assertSame(array('PUNBB.common.addDOMReadyEvent(PUNBB.common.initToggleCheckboxes);'), $this->kit->chromes->scripts);

		$this->kit->visitor->administrator = false;
		$this->kit->visitor->permissions = array(GroupPermission::Moderate, GroupPermission::BanUsers);
		$this->assertStringContainsString('<p class="options"><span class="submit first-item"><input type="submit" name="ban_users" value="Ban" /></span></p>', $this->page(array('show_users' => '192.0.2.3')));

		$this->kit->visitor->permissions = array(GroupPermission::Moderate);
		$this->assertStringNotContainsString('main-options', $this->page(array('show_users' => '192.0.2.3')));

		$empty = $this->page(array('show_users' => '192.0.2.99'));
		$this->assertStringContainsString("<h2 class=\"hn\"><span>Users found [ 0 ]</span></h2>", $empty);
		$this->assertStringNotContainsString('select-all', $empty);
		$this->assertStringContainsString('<td class="tc0">The supplied IP address could not be found in the database.</td>', $empty);
	}

	public function testASearchIsCheckedBeforeItsUsersAreListedAPageAtATime(): void {
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('find_user' => '1', 'order_by' => 'password', 'direction' => 'ASC')));
		$this->assertStringContainsString('<p>You entered a non-numeric value into a numeric only column.</p>', $this->page(array('find_user' => '1', 'order_by' => 'username', 'direction' => 'ASC', 'posts_greater' => '1x')));
		$this->assertStringContainsString('<p>You entered an invalid date/time.</p>', $this->page(array('find_user' => '1', 'order_by' => 'username', 'direction' => 'ASC', 'registered_after' => 'someday')));
		$this->assertStringContainsString('<p>You didn\'t enter any search terms.</p>', $this->page(array('find_user' => '1', 'order_by' => 'username', 'direction' => 'ASC', 'form' => array('username' => ' '))));
		$this->assertSame(4, count(array_keys($this->kit->events->dispatched, 'SearchSelected', true)) + 1, 'the order is checked before a search is selected, the criteria after');

		$this->kit->visitor->topicsPerPage = 2;
		$this->users->found = array(
			new FoundUser(3, 'member', 'member@example.com', '', 3, '', 3, null),
			new FoundUser(4, 'newbie', 'new@example.com', '', 0, '', null, null),
			new FoundUser(5, 'banned', 'banned@example.com', 'Banned', 0, '', 0, null),
		);

		$this->kit->chromes->opened = array();
		$body = $this->page(array('find_user' => '1', 'order_by' => 'last_post', 'direction' => 'DESC', 'form' => array('username' => 'mem*', 'realname' => 'x & y'), 'last_post_after' => '2020-01-01 10:00', 'p' => '2'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-uresults', 'users', 'find_user', 2), array($head->id, $head->section, $head->view, $head->page));
		$this->assertSame('<p class="paging"><span class="pages">Pages</span> [pages 2 of 2 at admin_users?find_user=&amp;order_by=last_post&amp;direction=DESC&amp;user_group=-1&amp;last_post_after=2020-01-01%2010%3A00&amp;form%5Busername%5D=mem%2A&amp;form%5Brealname%5D=x+%26+y in query]</p>', $head->pagePost['paging']->html);
		$this->assertSame(array('mem*', 'x & y', -1, 'last_post', true), array($this->users->searched?->fields()[0]->text(), $this->users->searched?->fields()[1]->text(), $this->users->searched?->groupId(), $this->users->searched?->orderBy(), $this->users->searched?->descending()));
		$this->assertSame(array('find from 2 at most 2'), $this->users->log);
		$this->assertStringContainsString("<form id=\"aus-find-user-results-form\" class=\"frm-form\" method=\"post\" accept-charset=\"utf-8\" action=\"/admin_users?a=1&amp;b=2?action=modify_users\">\n\t<div class=\"main-content main-forum\">", $body);
		$this->assertStringContainsString("<h2 class=\"hn\"><span>Users found [ 3 ]</span></h2>", $body);
		$this->assertStringContainsString("<tr class=\"odd row1\">\n\t\t\t\t\t<td class=\"tc0\"><span><a href=\"/user/5?a=1&amp;b=2\">banned</a></span>", $body);
		$this->assertStringContainsString('<td class="tc1">Banned</td>', $body, 'a ban titles an unverified user');

		$this->users->log = array();
		$first = $this->page(array('find_user' => '1', 'order_by' => 'username', 'direction' => 'ASC', 'user_group' => '0'));
		$this->assertSame(array('find from 0 at most 2'), $this->users->log);
		$this->assertStringContainsString('<td class="tc1">[title of member]</td>', $first);
		$this->assertStringContainsString('<td class="tc1"><strong>Not verified</strong></td>', $first);

		$this->users->found = array();
		$this->assertStringContainsString("<tr class=\"odd row1\">\n\t\t\t\t\t<td class=\"tc0\">No match</td>", $this->page(array('find_user' => '1', 'order_by' => 'username', 'direction' => 'ASC', 'posts_less' => '0')));
	}

	public function testUsersAreDeletedOnceTheDeletionIsConfirmed(): void {
		$this->assertStringStartsWith('302 /admin_users?a=1&b=2 ', $this->page(array('action' => 'modify_users'), array('delete_users_cancel' => '1')));

		$this->kit->visitor->administrator = false;
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('action' => 'modify_users'), array('delete_users' => '1', 'users' => array(3 => '1'))));

		$this->kit->visitor->administrator = true;
		$this->assertStringContainsString('<p>No users selected.</p>', $this->page(array('action' => 'modify_users'), array('delete_users' => '1', 'users' => '0')));
		$this->assertStringContainsString('<p>Administrators cannot be deleted.', $this->page(array('action' => 'modify_users'), array('delete_users' => '1', 'users' => array(3 => '1', 2 => '1'))));

		$this->kit->chromes->opened = array();
		$form = $this->page(array('action' => 'modify_users'), array('delete_users' => '1', 'users' => array(3 => '1', 4 => '1')));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-users', 'users', 'delete'), array($head->id, $head->section, $head->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Users', 'Searches', 'Delete users'), $this->crumbs());
		$this->assertStringContainsString("<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/admin_users?a=1&amp;b=2?action=modify_users')."\" />\n\t\t\t\t<input type=\"hidden\" name=\"users\" value=\"3,4\" />", $form);
		$this->assertStringContainsString('<input type="checkbox" id="fld1" name="delete_posts" value="1" checked="checked" />', $form);
		$this->assertStringEndsWith("<span class=\"cancel\"><input type=\"submit\" name=\"delete_users_cancel\" value=\"Cancel\" /></span>\n\t\t\t</div>\n\t\t</form>\n\t</div>", $form);

		$this->kit->events->dispatched = array();
		$done = $this->page(array('action' => 'modify_users'), array('delete_users_comply' => '1', 'users' => '1,3,4', 'delete_posts' => '1'));

		$this->assertStringStartsWith('302 /admin_users?a=1&b=2 ', $done);
		$this->assertSame(array('remove 3 with posts', 'remove 4 with posts'), $this->users->log);
		$this->assertSame(array('UsersRequested', 'DeleteUsersStep', 'DeleteUsersStep', 'DeleteUsersStep', 'RedirectShowing', 'RedirectHeadAssembling'), $this->kit->events->dispatched);
	}

	public function testUsersAreBannedAtTheAddressTheyPostedFromLast(): void {
		$this->kit->visitor->administrator = false;
		$this->kit->visitor->permissions = array(GroupPermission::Moderate);
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('action' => 'modify_users'), array('ban_users' => '1', 'users' => array(3 => '1'))));

		$this->kit->visitor->permissions = array(GroupPermission::Moderate, GroupPermission::BanUsers);
		$this->assertStringContainsString('<p>One of the selected users is an administrator', $this->page(array('action' => 'modify_users'), array('ban_users' => '1', 'users' => array(2 => '1'))));

		$this->kit->chromes->opened = array();
		$form = $this->page(array('action' => 'modify_users'), array('ban_users' => '1', 'users' => array(3 => '1')));
		$this->assertSame(array('admin-users', 'ban'), array($this->kit->chromes->opened[0]->id, $this->kit->chromes->opened[0]->view));
		$this->assertSame(array('Board & Co', 'Administration', 'Searches', 'Ban users'), $this->crumbs());
		$this->assertStringContainsString('<label for="fld1"><span>Ban message</span>', $form);
		$this->assertStringContainsString("<div class=\"sf-set set2\">\n\t\t\t\t\t<div class=\"sf-box text\">\n\t\t\t\t\t\t<label for=\"fld2\"><span>Ban expiry date</span>", $form);

		$this->assertStringContainsString('<p>You entered an invalid expire date.', $this->page(array('action' => 'modify_users'), array('ban_users_comply' => '1', 'users' => '3', 'ban_expire' => '2001-01-01')));
		$this->assertSame(array(), $this->users->log);

		$this->users->postAddresses = array(new PostAddress(3, '198.51.100.1'), new PostAddress(3, '198.51.100.2'), new PostAddress(4, ''));
		$done = $this->page(array('action' => 'modify_users'), array('ban_users_comply' => '1', 'users' => '1,3,4', 'ban_message' => ' Bye ', 'ban_expire' => 'Never'));

		$this->assertStringStartsWith('302 /admin_users?a=1&b=2 ', $done);
		$this->assertSame(array("ban 3 user3 at 198.51.100.2 user3@example.com 'Bye' until NULL by 2", "ban 4 user4 at 192.0.2.4 user4@example.com 'Bye' until NULL by 2", 'bans cache'), $this->users->log);
		$this->assertSame(array('Users banned.'), $this->kit->flash->info);
	}

	public function testUsersMoveIntoAGroupThatExists(): void {
		$this->assertStringStartsWith('302 /admin_users?a=1&b=2 ', $this->page(array('action' => 'modify_users'), array('change_group_cancel' => '1')));

		$form = $this->page(array('action' => 'modify_users'), array('change_group' => '1', 'users' => array(3 => '1', 4 => '1')));
		$this->assertSame('change_group', $this->kit->chromes->opened[0]->view);
		$this->assertStringContainsString("<select id=\"fld1\" name=\"move_to_group\">\n\t\t\t\t\t\t\t\t<option value=\"1\">Administrators</option>\n\t\t\t\t\t\t\t\t<option value=\"3\" selected=\"selected\">Members &lt;b&gt;</option>\n\t\t\t\t\t\t\t\t<option value=\"4\">Moderators</option>\n\t\t\t\t\t\t</select>", $form);

		$bad = '<p>Bad request. The link you followed is incorrect or outdated.</p>';
		$this->assertStringContainsString($bad, $this->page(array('action' => 'modify_users'), array('change_group_comply' => '1', 'users' => '3', 'move_to_group' => '2')));
		$this->assertStringContainsString($bad, $this->page(array('action' => 'modify_users'), array('change_group_comply' => '1', 'users' => '3', 'move_to_group' => '9')));
		$this->assertSame(array(), $this->users->log);

		$this->page(array('action' => 'modify_users'), array('change_group_comply' => '1', 'users' => '3,4', 'move_to_group' => '4'));
		$this->page(array('action' => 'modify_users'), array('change_group_comply' => '1', 'users' => '3,4', 'move_to_group' => '1'));
		$this->page(array('action' => 'modify_users'), array('change_group_comply' => '1', 'users' => '3,4', 'move_to_group' => '3'));

		$this->assertSame(array('move 3,4 to 4', 'move 3,4 to 1', 'move 3,4 to 3', 'moderators'), $this->users->log);
	}
}
