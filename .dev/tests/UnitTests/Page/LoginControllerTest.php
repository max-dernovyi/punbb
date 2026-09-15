<?php
/**
 * login.php as a module, built with no forum: the login form and what stops a
 * login, signing in and out, a signed-in member sent to the index, and the
 * form asking for a new password with the keys mailed once it is answered.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Login\Event\LoginRendering;
use PunBB\Module\Login\Event\LoginStep;
use PunBB\Module\Login\Event\LogoutStep;
use PunBB\Module\Login\Event\PasswordRequestRendering;
use PunBB\Module\Login\Event\PasswordRequestStep;
use PunBB\Module\Login\Event\PasswordResetMailing;
use PunBB\Module\Login\Model\Credentials;
use PunBB\Module\Login\Model\ResettableAccount;

require_once __DIR__.'/LoginFakes.php';

class LoginControllerTest extends TestCase {
	private LoginKit $login;

	protected function setUp(): void {
		$this->login = new LoginKit();
		$this->login->services->credentials = array(
			'anna'	=> new Credentials(5, 3, 'hash:right', 'salt5'),
			'old'	=> new Credentials(6, 0, 'old:right', ''),
			'none'	=> new Credentials(7, 3, '', ''),
		);
	}

	/** @param array<string, mixed> $post */
	private function signIn(array $post): string {
		return $this->login->page(array(), $post + array('form_sent' => '1'));
	}

	public function testAGuestGetsTheLoginForm(): void {
		$this->login->kit->settings->values['o_board_title'] = 'Board & <Co>';

		$body = $this->login->page();

		$head = $this->login->kit->chromes->opened[0];
		$this->assertSame('login', $head->id);
		$this->assertSame(array('Board & <Co>', 'Login to Board & <Co>'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$this->assertStringStartsWith("200  [login]<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>Login to Board &amp; &lt;Co&gt;</span></h2>", $body);
		$this->assertStringContainsString('<p class="hn">Do you need to <a href="/register?a=1&amp;b=2">register</a> or obtain a <a href="/request_password?a=1&amp;b=2">new password</a> before you login?</p>', $body);
		$this->assertStringContainsString("<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"form_sent\" value=\"1\" />\n\t\t\t\t<input type=\"hidden\" name=\"redirect_url\" value=\"http://forum.test/before?a=1&amp;b=&quot;2&quot;\" />\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/login?a=1&amp;b=2')."\" />\n\t\t\t</div>", $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld3" name="save_pass" value="1" /></span>', $body);
		$this->assertStringNotContainsString('error-box">'."\n\t\t\t<h2", $body);
		$this->assertStringEndsWith("<input type=\"submit\" name=\"login\" value=\"Login\" /></span>\n\t\t\t</div>\n\t\t</form>\n\t</div>", $body);
	}

	public function testASignedInMemberAskingForAFormIsSentToTheIndexWithNothingElse(): void {
		$this->login->kit->visitor->guest = false;

		$this->assertSame('302 /index?a=1&b=2 ', $this->login->page());
		$this->assertSame('302 /index?a=1&b=2 ', $this->login->page(array('action' => 'forget'), array('form_sent' => '1', 'req_email' => 'anna@example.com')));
		$this->assertSame(array(), $this->login->kit->chromes->opened);
		$this->assertSame(array(), $this->login->services->log);
		$this->assertSame(array(), $this->login->services->deferred);
	}

	public function testAWrongPasswordShowsTheFormAgainWithWhatWasTyped(): void {
		$body = $this->signIn(array('req_username' => ' anna ', 'req_password' => 'w"rong', 'save_pass' => '1'));

		$this->assertSame(array('credentials anna', 'verify'), $this->login->services->log);
		$this->assertStringContainsString("<ul class=\"error-list\">\n\t\t\t\t<li class=\"warn\"><span>Incorrect username and/or password.</span></li>\n\t\t\t</ul>", $body);
		$this->assertStringContainsString('name="req_username" value=" anna "', $body);
		$this->assertStringContainsString('name="req_password" value="w&quot;rong"', $body);
		$this->assertStringContainsString('name="save_pass" value="1" checked="checked" />', $body);
	}

	public function testAnAccountThatIsNotThereOrHasNoPasswordCostsAVerificationToo(): void {
		$this->signIn(array('req_username' => 'nobody', 'req_password' => 'right'));
		$this->signIn(array('req_username' => 'none', 'req_password' => 'right'));

		$this->assertSame(array('credentials nobody', 'verify against nobody', 'credentials none', 'verify against nobody'), $this->login->services->log);
	}

	public function testTheRightPasswordSignsTheMemberInWithANewSessionAndSendsThemBack(): void {
		$steps = array();
		$this->login->kit->events->observe(LoginStep::class, function (LoginStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.($event->authorized() ? 'authorized' : 'unauthorized').' '.count($this->login->services->log);
		});

		$response = $this->signIn(array('req_username' => 'anna', 'req_password' => 'right', 'redirect_url' => 'http://forum.test/topic?id=1&x="y"'));

		$this->assertStringStartsWith('302 http://forum.test/topic?id=1&x=&quot;y&quot;&login=1 [redirect]', $response);
		$this->assertSame(array('credentials anna', 'verify', 'end guest visit 192.0.2.7', 'regenerate session', 'sign in 5 hash:right salt5 for 1800'), $this->login->services->log);
		$this->assertSame(array('submitted unauthorized 0', 'checked authorized 2', 'signed_in authorized 5'), $steps);
	}

	public function testAnOlderHashIsStoredAgainAndAFirstLoginVerifiesTheAccount(): void {
		$response = $this->signIn(array('req_username' => 'old', 'req_password' => 'right', 'save_pass' => '1'));

		$this->assertStringStartsWith('302 http://forum.test/?login=1 [redirect]', $response);
		$this->assertSame(array('credentials old', 'verify', 'store 6 hash:right key12', 'activate 6 into 4', 'end guest visit 192.0.2.7', 'regenerate session', 'sign in 6 hash:right key12 for 1209600'), $this->login->services->log);
	}

	public function testObserversStopALoginOrLetNobodyInWhoIsNotThere(): void {
		$this->login->kit->events->observe(LoginStep::class, function (LoginStep $event): void {
			if ($event->step() === LoginStep::SUBMITTED && $event->username() === 'anna')
				$event->setErrors(array('<b>Captcha</b>'));

			if ($event->step() === LoginStep::CHECKED && $event->username() === 'nobody')
				$event->setErrors(array());
		});

		$this->assertStringContainsString("<li class=\"warn\"><span><b>Captcha</b></span></li>\n", $this->signIn(array('req_username' => 'anna', 'req_password' => 'right')));
		$this->assertStringContainsString('Incorrect username and/or password.', $this->signIn(array('req_username' => 'nobody', 'req_password' => 'right')));
		$this->assertNotContains('regenerate session', $this->login->services->log);

		$this->expectException(InvalidArgumentException::class);
		(new LoginStep(LoginStep::CHECKED, 'nobody', false))->authorize(true);
	}

	public function testObserversChangeTheFormsFieldsAndErrorsAndTheFormNumbersOn(): void {
		$this->login->kit->events->observe(LoginRendering::class, function (LoginRendering $event): void {
			if ($event->position() === LoginRendering::OUTPUT_START)
				$event->remove(LoginRendering::HIDDEN_FIELDS, 'redirect_url');

			if ($event->position() === LoginRendering::PRE_LOGIN_ERRORS)
				$event->set(LoginRendering::ERRORS, 'probe', '<li>probe</li>');

			if ($event->position() === LoginRendering::PRE_PASS)
			{
				$event->append('<input id="fld'.($event->fieldCount() + 1).'" />');
				$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
			}
		});

		$body = $this->signIn(array('req_username' => 'anna', 'req_password' => 'wrong'));

		$this->assertStringNotContainsString('name="redirect_url"', $body);
		$this->assertStringContainsString("<li class=\"warn\"><span>Incorrect username and/or password.</span></li>\n\t\t\t\t<li>probe</li>\n", $body);
		$this->assertStringContainsString('<input id="fld2" />'."\t\t\t\t<div class=\"sf-set set3\">", $body);
		$this->assertStringContainsString('<input type="password" id="fld3" name="req_password"', $body);
	}

	public function testAMemberSignsOutByTheirOwnLinkOnly(): void {
		$this->login->kit->visitor->guest = false;
		$this->login->kit->visitor->id = 3;

		$this->assertSame('302 /index?a=1&b=2 ', $this->login->page(array('action' => 'out', 'id' => '4', 'csrf_token' => 'token-for-'.md5('logout3'))));
		$this->assertSame('302 /index?a=1&b=2 ', $this->login->page(array('action' => 'out', 'id' => array('3'))));

		$confirm = $this->login->page(array('action' => 'out', 'id' => '3', 'csrf_token' => 'x'));
		$this->assertStringContainsString('name="prev_url"', $confirm);
		$this->assertSame(array(), $this->login->services->log);

		$steps = array();
		$this->login->kit->events->observe(LogoutStep::class, function (LogoutStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->userId().' '.count($this->login->services->log);
		});

		$this->assertStringStartsWith('302 /index?a=1&b=2 [redirect]', $this->login->page(array('action' => 'out', 'id' => '3', 'csrf_token' => 'token-for-'.md5('logout3'))));
		$this->assertSame(array('end visit 3', 'last visit 3 at 5000', 'regenerate session', 'sign out'), $this->login->services->log);
		$this->assertTrue($this->login->kit->visitor->forgotTracked);
		$this->assertSame(array('selected 3 0', 'signed_out 3 4'), $steps);
	}

	public function testAGuestGetsTheFormAskingForANewPasswordAndItsErrors(): void {
		$this->login->kit->events->observe(PasswordRequestStep::class, function (PasswordRequestStep $event): void {
			if ($event->step() === PasswordRequestStep::VALIDATED)
				$event->setErrors(array_merge($event->errors(), array('<em>probe</em>')));
		});
		$this->login->kit->events->observe(PasswordRequestRendering::class, function (PasswordRequestRendering $event): void {
			if ($event->position() === PasswordRequestRendering::PRE_NEW_PASSWORD_ERRORS)
				$event->remove('0');
		});

		$body = $this->login->page(array('action' => 'forget'));

		$this->assertSame(array('reqpass', array('Board & Co', 'New password request')), array($this->login->kit->chromes->opened[0]->id, array_map(static fn ($crumb): string => $crumb->text, $this->login->kit->chromes->opened[0]->crumbs)));
		$this->assertStringContainsString("<input type=\"hidden\" name=\"form_sent\" value=\"1\" />\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5('/request_password?a=1&amp;b=2')."\" />", $body);
		$this->assertStringContainsString('<input id="fld1" type="email" name="req_email" value="" size="35"', $body);

		$errors = $this->login->page(array('action' => 'forget_2'), array('form_sent' => '1', 'req_email' => 'not an <address>'));
		$this->assertStringContainsString("<ul class=\"error-list\">\n\t\t\t\t<li class=\"warn\"><span><em>probe</em></span></li>\n\t\t\t</ul>", $errors);
		$this->assertStringContainsString('name="req_email" value="not an &lt;address&gt;"', $errors);
		$this->assertSame(array(), $this->login->services->deferred);

		$this->assertStringStartsWith('302 /index?a=1&b=2 [redirect]', $this->login->page(array('action' => 'forget'), array('form_sent' => '1', 'cancel' => '1')));
	}

	public function testTheKeysAreMailedOnlyOnceTheAnswerIsDeliveredAndNotToAnAdministratorOrTwice(): void {
		$recent = time() - 60;
		$this->login->services->resettable['anna@example.com'] = array(
			new ResettableAccount(5, 3, 'anna', null),
			new ResettableAccount(2, 1, 'admin', null),
			new ResettableAccount(8, 3, 'again', $recent),
			new ResettableAccount(9, 3, 'annie', time() - 7200),
		);

		$body = $this->login->page(array('action' => 'forget'), array('form_sent' => '1', 'req_email' => ' Anna@Example.com '));

		$this->assertStringContainsString('<p>If that email address belongs to an account, a message with instructions on how to change the password has been sent to it. If it does not arrive you can contact the forum administrator at <a href="mailto:admin@example.com">admin@example.com</a>.</p>', $body);
		$this->assertSame(array('resettable anna@example.com'), $this->login->services->log);
		$this->assertSame(array(), $this->login->services->mail);

		$steps = array();
		$this->login->kit->events->observe(PasswordResetMailing::class, function (PasswordResetMailing $event) use (&$steps): void {
			$steps[] = $event->step().($event->account() !== null ? ' '.$event->account()->username() : '');

			if ($event->step() === PasswordResetMailing::COMPOSED)
				$event->compose('['.$event->subject().']', $event->message());

			if ($event->step() === PasswordResetMailing::ADDRESSED && $event->account()?->username() === 'annie')
				$event->address($event->message().' (probed)');
		});

		$this->login->services->runDeferred();

		$this->assertSame(array('resettable anna@example.com', 'reset key 5 key8r', 'reset key 9 key8r'), $this->login->services->log);
		$this->assertSame(array('starting', 'composed', 'checking anna', 'addressed anna', 'checking admin', 'checking again', 'checking annie', 'addressed annie'), $steps);
		$this->assertSame(array(
			array('anna@example.com', '[New password requested]', "Hello anna,\n\nVisit /change_password_key/5/key8r?a=1&b=2 at http://forum.test/.\n\n--\nBoard & Co Mailer", true),
			array('anna@example.com', '[New password requested]', "Hello annie,\n\nVisit /change_password_key/9/key8r?a=1&b=2 at http://forum.test/.\n\n--\nBoard & Co Mailer (probed)", true),
		), $this->login->services->mail);
	}

	public function testAnAddressWithoutAccountsGetsTheSameAnswerAndNothingIsMailed(): void {
		$this->login->services->resettable['anna@example.com'] = array(new ResettableAccount(5, 3, 'anna', null));

		$known = $this->login->page(array('action' => 'forget'), array('form_sent' => '1', 'req_email' => 'anna@example.com'));
		$unknown = $this->login->page(array('action' => 'forget'), array('form_sent' => '1', 'req_email' => 'nobody@example.com'));

		$this->assertSame($known, $unknown);

		$this->login->services->deferred = array_slice($this->login->services->deferred, 1);
		$this->login->services->runDeferred();

		$this->assertSame(array(), $this->login->services->mail);
		$this->assertSame(array('resettable anna@example.com', 'resettable nobody@example.com'), $this->login->services->log);
	}
}
