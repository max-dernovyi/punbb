<?php
/**
 * admin/bans.php as a module, built with no forum: who may ban, the list of
 * bans a page at a time with every value escaped, the form for a new ban
 * filled in from a member or blank, the form editing a ban, saving a ban with
 * what it refuses, and removing a ban by its link.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Bans\Api\BanCandidatesInterface;
use PunBB\Module\Bans\Api\BansInterface;
use PunBB\Module\Bans\Api\Data\BanCandidateInterface;
use PunBB\Module\Bans\Api\Data\BanInterface;
use PunBB\Module\Bans\Controller\BansController;
use PunBB\Module\Bans\Event\BanAssembling;
use PunBB\Module\Bans\Event\BanChangeStep;
use PunBB\Module\Bans\Event\BanFormRendering;
use PunBB\Module\Bans\Event\BansRendering;
use PunBB\Module\Bans\Event\BansRequested;
use PunBB\Module\Bans\Event\BanTargetSelecting;
use PunBB\Module\Bans\Model\Ban;
use PunBB\Module\Bans\Model\BanCandidate;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\ConfirmFormRendering;
use PunBB\Module\Message\Event\ConfirmFormRequested;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Site\Cache\BanCacheInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Visitor\GroupPermission;

require_once __DIR__.'/PageFakes.php';

final class FakeBans implements BansInterface, BanCandidatesInterface, BanCacheInterface, EmailAddressesInterface {
	/** @var list<Ban> */
	public array $bans = array();

	/** @var array<int, BanCandidate> */
	public array $members = array();

	/** @var list<string> */
	public array $log = array();

	public function count(): int {
		return count($this->bans);
	}

	public function page(int $offset, int $limit): array {
		$this->log[] = 'page '.$offset.' '.$limit;

		return array_slice($this->bans, $offset, $limit);
	}

	public function find(int $id): ?BanInterface {
		foreach ($this->bans as $ban)
			if ($ban->id() === $id)
				return $ban;

		return null;
	}

	public function add(BanInterface ...$bans): void {
		foreach ($bans as $ban)
			$this->log[] = 'add '.self::described($ban);
	}

	public function update(BanInterface ...$bans): void {
		foreach ($bans as $ban)
			$this->log[] = 'update '.self::described($ban);
	}

	public function remove(int ...$ids): void {
		$this->log[] = 'remove '.implode(',', $ids);
	}

	public function byId(int $userId): ?BanCandidateInterface {
		return $this->members[$userId] ?? null;
	}

	public function byUsername(string $username): ?BanCandidateInterface {
		foreach ($this->members as $member)
			if ($member->username() === $username)
				return $member;

		return null;
	}

	public function lastKnownIp(int $userId): ?string {
		return $userId === 3 ? '192.0.2.30' : null;
	}

	public function rebuild(): void {
		$this->log[] = 'rebuild';
	}

	public function isValid(string $address): bool {
		return str_contains($address, '@') && !str_contains($address, ' ');
	}

	public function isBanned(string $address): bool {
		return false;
	}

	private static function described(BanInterface $ban): string {
		return implode('|', array($ban->id(), var_export($ban->username(), true), var_export($ban->ip(), true), var_export($ban->email(), true), var_export($ban->message(), true), $ban->expire() !== null ? 'expires' : 'NULL', $ban->creatorId()));
	}
}

class BansControllerTest extends TestCase {
	private PageKit $kit;

	private FakeBans $bans;

	protected function setUp(): void {
		$this->kit = new PageKit(array(BansRequested::class, BanTargetSelecting::class, BanChangeStep::class, BanFormRendering::class, BansRendering::class, BanAssembling::class,
			MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class, ConfirmFormRequested::class, ConfirmFormRendering::class));
		$this->kit->language->real = array('admin_common', 'admin_bans', 'common');
		$this->kit->settings->values['o_redirect_delay'] = '0';
		$this->kit->visitor->administrator = true;

		$this->bans = new FakeBans();
		$this->bans->bans = array(
			new Ban(1, 'spam<mer>', null, null, 'Go "away"', null, 2, 'ad<min>'),
			new Ban(2, null, '198.51.100.7 <b>', 'banned.invalid', null, 2000, 9, null),
		);
		$this->bans->members = array(
			2 => new BanCandidate(2, 1, 'admin', 'admin@example.com', '192.0.2.1'),
			3 => new BanCandidate(3, 3, 'An<na>', 'Anna@Example.com', '192.0.2.3'),
			4 => new BanCandidate(4, 3, 'bob', 'bob@example.com', '192.0.2.4'),
		);
	}

	private function page(array $query = array(), array $post = array()): string {
		$confirmations = new ConfirmPage($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->redirects(), $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->visitor, $this->kit->tokens);
		$controller = new BansController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(), $confirmations,
			$this->bans, $this->bans, $this->bans, $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter, $this->kit->tokens, $this->kit->flash, $this->bans);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'admin/bans.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testAModeratorBansOnlyWhenTheirGroupMay(): void {
		$this->kit->visitor->administrator = false;
		$this->kit->visitor->permissions[] = GroupPermission::Moderate;

		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('del_ban' => '1')));

		$this->kit->visitor->permissions[] = GroupPermission::BanUsers;
		$this->page();
		$this->assertSame(array('Board & Co', 'Administration', 'Bans'), array_map(static fn ($crumb): string => $crumb->text, $this->kit->chromes->opened[1]->crumbs));
	}

	public function testTheBansAreListedWithEveryValueEscaped(): void {
		$body = $this->page();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-bans', 'users', null, 1), array($head->id, $head->section, $head->view, $head->page));
		$this->assertSame(array('Board & Co', 'Administration', 'Users', 'Bans'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));
		$this->assertSame('<p class="paging"><span class="pages">Pages</span> [pages 1 of 1 at admin_bans in query]</p>', $head->pagePost['paging']->html);
		$this->assertSame(array(), $head->navigation);

		$action = '/admin_bans?a=1&amp;b=2&amp;action=more';
		$this->assertStringContainsString('<form class="frm-form" method="post" accept-charset="utf-8" action="'.$action.'">'."\n\t\t\t".'<div class="hidden">'."\n\t\t\t\t".'<input type="hidden" name="csrf_token" value="token-for-'.md5($action).'" />', $body);
		$this->assertStringContainsString('<input type="text" id="fld1" name="new_ban_user" size="25" maxlength="25" />', $body);
		$this->assertStringContainsString('<h3><span>Banned by <a href="/user/2?a=1&amp;b=2">ad&lt;min&gt;</a></span></h3>', $body);
		$this->assertStringContainsString('<p><a href="/admin_bans?a=1&amp;b=2&amp;edit_ban=1">Edit ban</a> or <a href="/admin_bans?a=1&amp;b=2&amp;del_ban=1&amp;csrf_token=token-for-'.md5('del_ban13').'">Remove ban</a></p>', $body);
		$this->assertStringContainsString("<ul>\n\t\t\t\t\t<li><span>Username:</span> <strong>spam&lt;mer&gt;</strong></li>\n<li><span>Message:</span> <strong>Go &quot;away&quot;</strong></li>\n\t\t\t\t\t</ul>", $body);
		$this->assertStringContainsString('<h3><span>Banned by Unknown</span></h3>', $body);
		$this->assertStringContainsString('<li><span>IP/IP-ranges:</span> <strong>198.51.100.7 &lt;b&gt;</strong></li>', $body);
		$this->assertStringContainsString('<li><span>Expires:</span> <strong><time>2000</time></strong></li>', $body);
		$this->assertSame(array('page 0 2'), $this->bans->log, 'as many as there are, up to a page');
	}

	public function testTheListPagesByTheVisitorsTopicsPerPage(): void {
		$this->kit->visitor->topicsPerPage = 1;

		$body = $this->page(array('p' => '2'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(2, $head->page);
		$this->assertSame(array('prev', 'first'), array_keys($head->navigation));
		$this->assertStringContainsString('<div class="ct-set set1">', $body);
		$this->assertStringContainsString('<strong>banned.invalid</strong>', $body);
		$this->assertSame(array('page 1 1'), $this->bans->log);

		$this->bans->bans = array();
		$this->assertStringContainsString("<div class=\"main-content main-frm\">\n\t\t<div class=\"ct-box\">\n\t\t\t<p>No bans in list.</p>", $this->page());
	}

	public function testObserversChangeTheHiddenFieldsTheCountsAndABansLines(): void {
		$this->kit->events->observe(BansRendering::class, function (BansRendering $event): void {
			if ($event->position() === BansRendering::MAIN_OUTPUT_START)
			{
				$event->set('probe', '<input type="hidden" name="probe" />');
				$event->count($event->groupCount() + 1, $event->itemCount(), $event->fieldCount() + 2);
			}
		});
		$this->kit->events->observe(BanAssembling::class, function (BanAssembling $event): void {
			$event->remove('message');
			$event->setCreator('<em>'.$event->number().'</em>');
		});

		$body = $this->page();

		$this->assertStringContainsString("<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/admin_bans?a=1&amp;b=2&amp;action=more')."\" />\n\t\t\t\t<input type=\"hidden\" name=\"probe\" />", $body);
		$this->assertStringContainsString('<fieldset class="frm-group group2">', $body);
		$this->assertStringContainsString('<input type="text" id="fld3" name="new_ban_user"', $body);
		$this->assertStringNotContainsString('Go &quot;away&quot;', $body);
		$this->assertStringContainsString('<h3><span>Banned by <em>2</em></span></h3>', $body);
	}

	public function testALinkFromAProfileFillsTheFormInFromTheMember(): void {
		$targets = array();
		$this->kit->events->observe(BanTargetSelecting::class, function (BanTargetSelecting $event) use (&$targets): void {
			$targets[] = $event->target().' '.$event->userId();
		});

		$body = $this->page(array('add_ban' => '3'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-bans', 'form', null), array($head->id, $head->view, $head->page));
		$this->assertSame(array('Board & Co', 'Administration', 'Users', 'Bans', 'Ban advanced settings'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));
		$this->assertStringContainsString('<input type="hidden" name="mode" value="add" />'."\n\t\t\t".'</div>', $body);
		$this->assertStringContainsString('<input type="text" id="fld1" name="ban_user" size="40" maxlength="25" value="An&lt;na&gt;" />', $body);
		$this->assertStringContainsString('<input type="text" id="fld2" name="ban_email" size="40" maxlength="80" value="anna@example.com" />', $body);
		$this->assertStringContainsString(' Click the following link to see IP statistics for this user: <a href="/admin_users?a=1&amp;b=2?ip_stats=3">User IP statistics</a></small>', $body);
		$this->assertStringContainsString('name="ban_ip" size="40" maxlength="255" value="192.0.2.30" />', $body);
		$this->assertStringContainsString('<input type="text" id="fld5" name="ban_expire" size="20" maxlength="10" value="" />', $body);
		$this->assertStringContainsString('<input type="submit" name="add_edit_ban" value=" Save ban" />', $body);
		$this->assertSame(array('user 3'), $targets);

		$this->assertStringContainsString('name="ban_ip" size="40" maxlength="255" value="192.0.2.4" />', $this->page(array('add_ban' => '4')), 'without a post, the registration address');
	}

	public function testTheListsFormFindsTheMemberByNameOrStartsABanTiedToNobody(): void {
		$this->assertStringContainsString('value="bob@example.com"', $this->page(array(), array('add_ban' => '1', 'new_ban_user' => ' bob ')));

		$blank = $this->page(array(), array('add_ban' => '1', 'new_ban_user' => ''));
		$this->assertStringContainsString('name="ban_user" size="40" maxlength="25" value="" />', $blank);
		$this->assertStringNotContainsString('ip_stats', $blank);

		$this->assertStringContainsString('<p>No user by that username registered.', $this->page(array(), array('add_ban' => '1', 'new_ban_user' => 'nobody')));
		$this->assertStringContainsString('<p>No user by that ID registered.</p>', $this->page(array('add_ban' => '9')));
		$this->assertStringContainsString('<p>The user is an administrator and can\'t be banned.', $this->page(array('add_ban' => '2')));
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('add_ban' => '1')));
	}

	public function testABanIsEditedWithItsExpiryAsAGmtDate(): void {
		$this->bans->bans[1] = new Ban(2, null, '198.51.100.7', 'Banned.Invalid', null, 86400 * 365, 9, null);

		$body = $this->page(array('edit_ban' => '2'));

		$this->assertStringContainsString('<input type="hidden" name="mode" value="edit" />'."\n\t\t\t\t".'<input type="hidden" name="ban_id" value="2" />', $body);
		$this->assertStringContainsString('name="ban_user" size="40" maxlength="25" value="" />', $body);
		$this->assertStringContainsString('name="ban_email" size="40" maxlength="80" value="banned.invalid" />', $body);
		$this->assertStringContainsString('name="ban_expire" size="20" maxlength="10" value="1971-01-01" />', $body);
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('edit_ban' => '7')));
	}

	public function testTheFormNumbersOnFromTheFieldsObserversAdd(): void {
		$this->kit->events->observe(BanFormRendering::class, function (BanFormRendering $event): void {
			if ($event->position() === BanFormRendering::PRE_IP)
			{
				$event->append('<div class="sf-set set'.($event->itemCount() + 1).'"><input id="fld'.($event->fieldCount() + 1).'" /></div>'."\n");
				$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
			}
		});

		$body = $this->page(array('add_ban' => '4'));

		$this->assertStringContainsString("<div class=\"sf-set set3\"><input id=\"fld3\" /></div>\n\t\t\t\t<div class=\"sf-set set4\">", $body);
		$this->assertStringContainsString('<input type="text" id="fld6" name="ban_expire"', $body);
	}

	public function testABanIsSavedWithItsAddressesTidiedAndTheListRebuilt(): void {
		$steps = array();
		$this->kit->events->observe(BanChangeStep::class, function (BanChangeStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.(int) $event->adding().' '.var_export($event->ban()->ip(), true).' '.$event->submittedExpire();
		});

		$save = array('add_edit_ban' => '1', 'mode' => 'add', 'ban_user' => ' eve ', 'ban_ip' => '192.0.2.010   2001:0db8::0001', 'ban_email' => 'Eve@Example.com', 'ban_message' => '', 'ban_expire' => 'Never');
		$this->assertStringStartsWith('302 /admin_bans?a=1&b=2 [redirect]', $this->page(array(), $save));
		$this->assertStringStartsWith('302 ', $this->page(array(), array('mode' => 'edit', 'ban_id' => '2', 'ban_expire' => '') + $save));

		$this->assertSame(array(
			"add 0|'eve'|'192.0.2.10 2001:db8::1'|'eve@example.com'|NULL|NULL|3", 'rebuild',
			"update 2|'eve'|'192.0.2.10 2001:db8::1'|'eve@example.com'|NULL|NULL|3", 'rebuild',
		), $this->bans->log);
		$this->assertSame(array('Ban added.', 'Ban edited.'), $this->kit->flash->info);
		$this->assertSame(array("saving 1 '192.0.2.010   2001:0db8::0001' Never", "saved 1 '192.0.2.10 2001:db8::1' Never", "saving 0 '192.0.2.010   2001:0db8::0001' ", "saved 0 '192.0.2.10 2001:db8::1' "), $steps);
	}

	public function testABanThatBansNothingValidIsRefused(): void {
		$refused = array(
			array('You must enter at least one of the following pieces of information', array('ban_user' => ' ', 'ban_ip' => '', 'ban_email' => '')),
			array('The guest user cannot be banned.', array('ban_user' => 'GUEST')),
			array('You entered an invalid IP/IP-range.', array('ban_ip' => '192.0.2.256')),
			array('You entered an invalid IP/IP-range.', array('ban_ip' => '192.00.2.1')),
			array('You entered an invalid expire date.', array('ban_user' => 'eve', 'ban_expire' => '2001-01-01')),
		);

		foreach ($refused as [$message, $post])
			$this->assertStringContainsString($message, $this->page(array(), $post + array('add_edit_ban' => '1', 'mode' => 'add')));

		$this->assertStringContainsString('The email address (e.g. user@example.com)', $this->page(array(), array('add_edit_ban' => '1', 'ban_email' => 'not an address')));
		$this->assertStringStartsWith('302 ', $this->page(array(), array('add_edit_ban' => '1', 'mode' => 'add', 'ban_email' => 'example.org')), 'a domain bans every address at it');
		$this->assertSame(array("add 0|NULL|NULL|'example.org'|NULL|NULL|3", 'rebuild'), $this->bans->log);
	}

	public function testABanIsRemovedByItsLinkAndAStaleLinkIsConfirmedFirst(): void {
		$this->assertStringStartsWith('302 /admin_bans?a=1&b=2 [redirect]', $this->page(array('del_ban' => '1', 'csrf_token' => $this->kit->tokens->token('del_ban13'))));
		$this->assertSame(array('remove 1', 'rebuild'), $this->bans->log);
		$this->assertSame(array('Ban removed.'), $this->kit->flash->info);

		$this->assertStringContainsString('[dialogue]', $this->page(array('del_ban' => '2', 'csrf_token' => $this->kit->tokens->token('del_ban23x'))));
		$this->assertStringStartsWith('302 ', $this->page(array('del_ban' => '2'), array('csrf_token' => 'checked by the gate')));
		$this->assertSame(array('remove 1', 'rebuild', 'remove 2', 'rebuild'), $this->bans->log);
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('del_ban' => '0')));
	}
}
