<?php
/**
 * register.php as a module, built with no forum: who may register, the forum
 * rules first, the registration form and what stops a registration, and the
 * account stored with the member signed in, or mailed a key to verify it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Register\Api\Data\NewAccountInterface;
use PunBB\Module\Register\Api\RegistrationsInterface;
use PunBB\Module\Register\Controller\RegisterController;
use PunBB\Module\Register\Creation\AccountCreationInterface;
use PunBB\Module\Register\Event\RegistrationAlerting;
use PunBB\Module\Register\Event\RegistrationRendering;
use PunBB\Module\Register\Event\RegistrationRequested;
use PunBB\Module\Register\Event\RegistrationStep;
use PunBB\Module\Register\Event\RulesRendering;
use PunBB\Module\Register\Model\NewAccount;
use PunBB\Module\Site\Account\UsernameRulesInterface;

require_once __DIR__.'/LoginFakes.php';

final class FakeRegistrations implements RegistrationsInterface, AccountCreationInterface, UsernameRulesInterface {
	public int $recent = 0;

	/** @var list<string> */
	public array $duplicates = array();

	/** @var list<NewAccountInterface> */
	public array $added = array();

	/** @var list<string> */
	public array $log = array();

	public function registrationsFrom(string $address, int $since): int {
		$this->log[] = 'registrations from '.$address.' in '.(time() - $since);

		return $this->recent;
	}

	public function removeUnverified(int ...$registeredBefore): void {
		foreach ($registeredBefore as $before)
			$this->log[] = 'remove unverified older than '.(time() - $before);
	}

	public function usernamesWithEmail(string $email): array {
		return $this->duplicates;
	}

	public function add(NewAccountInterface $account): int {
		$this->added[] = $account;

		return 42;
	}

	public function validate(string $username, ?int $exceptUserId = null): array {
		return strlen($username) < 2 ? array(new Html('Username <b>too short</b>')) : array();
	}
}

class RegisterControllerTest extends TestCase {
	private PageKit $kit;

	private FakeLoginServices $services;

	private FakeRegistrations $registrations;

	protected function setUp(): void {
		$this->kit = new PageKit(array(RegistrationRequested::class, RegistrationStep::class, RegistrationAlerting::class, RulesRendering::class, RegistrationRendering::class,
			MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('common', 'profile');
		$this->kit->language->languages = array('English', 'Deutsch');
		$this->kit->visitor->guest = true;
		$this->kit->settings->values += array('o_redirect_delay' => '0', 'o_regs_allow' => '1', 'o_rules' => '0', 'o_regs_verify' => '0', 'o_mask_passwords' => '1',
			'o_default_timezone' => '2', 'o_default_dst' => '0', 'o_default_lang' => 'English', 'o_default_user_group' => '3', 'o_default_email_setting' => '1',
			'o_default_style' => 'Oxygen', 'o_timeout_visit' => '1800', 'o_admin_email' => 'admin@example.com', 'o_mailing_list' => '', 'p_allow_banned_email' => '1',
			'p_allow_dupe_email' => '0', 'o_regs_report' => '0', 'o_rules_message' => '<p>Be <em>nice</em>.</p>');
		$this->services = new FakeLoginServices();
		$this->registrations = new FakeRegistrations();
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 */
	private function page(array $query = array(), array $post = array()): string {
		$controller = new RegisterController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(),
			$this->registrations, $this->registrations, $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->tokens,
			$this->services, $this->services, $this->services, $this->registrations, $this->services, $this->services);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'register.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	/** @param array<string, mixed> $post */
	private function register(array $post): string {
		return $this->page(array('action' => 'register'), $post + array('form_sent' => '1', 'req_username' => 'anna', 'req_email1' => ' Anna@Example.com ', 'req_password1' => 'secret', 'req_password2' => 'secret'));
	}

	public function testAMemberIsSentToTheIndexAndAClosedBoardSaysSo(): void {
		$this->kit->visitor->guest = false;
		$this->assertSame('302 /index?a=1&b=2 ', $this->page());

		$this->kit->visitor->guest = true;
		$this->kit->settings->values['o_regs_allow'] = '0';
		$this->assertStringContainsString('<p>This forum is not accepting new registrations.</p>', $this->page());
	}

	public function testTheRulesComeFirstAndCancellingOrNotAgreeingGoesBack(): void {
		$this->kit->settings->values['o_rules'] = '1';

		$body = $this->page();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('rules-register', array('Board & Co', 'Register', 'Rules')), array($head->id, array_map(static fn ($crumb): string => $crumb->text, $head->crumbs)));
		$this->assertStringStartsWith("200  [rules-register]<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>Register at Board &amp; Co</span></h2>", $body);
		$this->assertStringContainsString("<div id=\"rules-content\" class=\"ct-box user-box\">\n\t\t\t<p>Be <em>nice</em>.</p>\t\t</div>", $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld1" name="req_agreement" value="1" required />', $body);

		$this->assertStringStartsWith('302 /index?a=1&b=2 [redirect]', $this->page(array('cancel' => '1')));
		$this->assertStringStartsWith('302 /index?a=1&b=2 [redirect]', $this->page(array('agree' => '1')));
		$this->assertStringContainsString('[register]', $this->page(array('agree' => '1', 'req_agreement' => '1')));
	}

	public function testTheRulesNumberTheirFieldsFromNoneWhateverTheStartLeft(): void {
		$this->kit->settings->values['o_rules'] = '1';
		$this->kit->events->observe(RulesRendering::class, function (RulesRendering $event): void {
			if ($event->position() === RulesRendering::OUTPUT_START)
				$event->count(0, 0, 7);

			if ($event->position() === RulesRendering::PRE_AGREE_CHECKBOX)
			{
				$event->append('<input id="fld'.($event->fieldCount() + 1).'" />');
				$event->count($event->groupCount(), $event->itemCount(), $event->fieldCount() + 1);
			}
		});

		$body = $this->page();

		$this->assertStringContainsString('<input id="fld1" />', $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld2" name="req_agreement"', $body);
	}

	public function testTheFormAsksForAPasswordTwiceAndOffersTheLanguages(): void {
		$body = $this->page(array(), array('form_sent' => '1', 'req_username' => 'a', 'req_email1' => 'x@example.com', 'req_password1' => 'se"cret', 'req_password2' => 'other', 'language' => 'Deutsch'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('register', array('Board & Co', 'Register at Board & Co')), array($head->id, array_map(static fn ($crumb): string => $crumb->text, $head->crumbs)));
		$this->assertSame(array(array('http://forum.test/include/js/punbb.timezone.js', false), array('PUNBB.timezone.detect_on_register_form();', true)), array_map(static fn ($script): array => array($script->code, $script->inline), $head->scripts));

		$this->assertStringNotContainsString('info-box', $body);
		$this->assertStringContainsString("<ul class=\"error-list\">\n\t\t\t\t<li class=\"warn\"><span>Username <b>too short</b></span></li>\n\t\t\t\t<li class=\"warn\"><span>Passwords do not match.</span></li>\n\t\t\t</ul>", $body);
		$this->assertStringContainsString("<input type=\"hidden\" name=\"timezone\" id=\"register_timezone\" value=\"2\" />", $body);
		$this->assertStringContainsString('<div class="sf-set set2 prepend-top">', $body);
		$this->assertStringContainsString('name="req_password1" size="35" value="se&quot;cret" required', $body);
		$this->assertStringContainsString("</div>\n\t\t\t\t\t\t<div class=\"sf-set set4\">", $body, 'the confirmation is set off by the tabs the page script printed');
		$this->assertStringContainsString("<select id=\"fld5\" name=\"language\">\n\t\t\t\t\t\t<option value=\"Deutsch\" selected=\"selected\">Deutsch</option>\n\t\t\t\t\t\t<option value=\"English\">English</option>\n", $body);
		$this->assertSame(array(), $this->registrations->added);
	}

	public function testAVerifiedRegistrationAsksForNoPasswordAndSaysAMailIsComing(): void {
		$this->kit->settings->values['o_regs_verify'] = '1';
		$this->kit->language->languages = array('English');

		$body = $this->page();

		$this->assertStringContainsString("<div class=\"ct-box info-box\">\n\t\t\t<p class=\"warn\"><strong>Important!</strong> An email with an activation link", $body);
		$this->assertStringNotContainsString('req_password1', $body);
		$this->assertStringNotContainsString('name="language"', $body);
		$this->assertStringContainsString('<div class="sf-set set2">', $body);
	}

	public function testTheAccountIsStoredAndTheMemberSignedIn(): void {
		$steps = array();
		$this->kit->events->observe(RegistrationStep::class, function (RegistrationStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->username().' '.$event->email().' '.count($this->registrations->added);
		});

		$response = $this->register(array('timezone' => '15', 'dst' => '1', 'language' => 'Deutsch'));

		$this->assertStringStartsWith('302 /index?a=1&b=2 [redirect]', $response);
		$this->assertSame(array('registrations from 192.0.2.7 in 3600', 'remove unverified older than 259200'), $this->registrations->log);
		$this->assertSame(array('submitted   0', 'validated anna anna@example.com 0', 'adding anna anna@example.com 0', 'added anna anna@example.com 1'), $steps);

		$account = $this->registrations->added[0];
		$this->assertSame(array('anna', 3, 'key12', 'secret', 'hash:secret', 'anna@example.com', 1, 2.0, 1, 'Deutsch', 'Oxygen', '192.0.2.7', null, false, false),
			array($account->username(), $account->groupId(), $account->salt(), $account->password(), $account->passwordHash(), $account->email(), $account->emailSetting(),
				$account->timezone(), $account->dst(), $account->language(), $account->style(), $account->registrationIp(), $account->activationKey(), $account->requiresVerification(), $account->notifiesAdmins()));
		$this->assertSame(array('sign in 42 hash:secret key12 for 1800'), $this->services->log);
	}

	public function testAVerifiedRegistrationMailsTheKeyInsteadOfSigningIn(): void {
		$this->kit->settings->values['o_regs_verify'] = '1';

		$body = $this->register(array());

		$this->assertStringContainsString('<p>Thank you for registering. An email has been sent to the specified address with instructions on how to activate your new account. If it doesn\'t arrive you can contact the forum administrator at <a href="mailto:admin@example.com">admin@example.com</a>.</p>', $body);
		$this->assertSame(array(0, 'key8r', 'key8r', true), array($this->registrations->added[0]->groupId(), $this->registrations->added[0]->password(), $this->registrations->added[0]->activationKey(), $this->registrations->added[0]->requiresVerification()));
		$this->assertSame(array(), $this->services->log);
	}

	public function testAFloodOrAnObserversErrorStopsTheRegistrationBeforeAnythingElse(): void {
		$this->registrations->recent = 1;
		$this->assertStringContainsString('<li class="warn"><span>A new user was registered with the same IP address', $this->register(array()));
		$this->assertSame(array('registrations from 192.0.2.7 in 3600'), $this->registrations->log);

		$this->registrations->recent = 0;
		$this->kit->events->observe(RegistrationStep::class, function (RegistrationStep $event): void {
			if ($event->step() === RegistrationStep::SUBMITTED)
				$event->setErrors(array('<b>Captcha</b>'));
		});

		$this->assertStringContainsString('<li class="warn"><span><b>Captcha</b></span></li>', $this->register(array()));
		$this->assertSame(array(), $this->registrations->added);
	}

	public function testADuplicateAddressIsRefusedUnlessTheBoardAllowsItThenTheListIsTold(): void {
		$this->registrations->duplicates = array('annie');
		$this->assertStringContainsString('Someone else is already registered with that email address.', $this->register(array()));

		$this->kit->settings->values['p_allow_dupe_email'] = '1';
		$this->kit->settings->values['o_mailing_list'] = 'list@example.com';
		$this->kit->events->observe(RegistrationAlerting::class, function (RegistrationAlerting $event): void {
			$event->compose('['.$event->subject().']', $event->message());
		});

		$this->register(array('req_email1' => 'anna@banned.invalid'));

		$this->assertSame(array(
			array('list@example.com', '[Alert - Banned e-mail detected]', "User 'anna' registered with banned e-mail address: anna@banned.invalid\n\nUser profile: /user/42?a=1&amp;b=2\n\n-- \nForum Mailer\n(Do not reply to this message)", false),
			array('list@example.com', '[Alert - Duplicate e-mail detected]', "User 'anna' registered with an e-mail address that also belongs to: annie\n\nUser profile: /user/42?a=1&amp;b=2\n\n-- \nForum Mailer\n(Do not reply to this message)", false),
		), $this->services->mail);
	}

	public function testALanguageTheBoardHasNotIsABadRequest(): void {
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->register(array('language' => 'Klingon')));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->register(array('language' => '../lang')));
		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->register(array('language' => array('English'))));
		$this->assertSame(array(), $this->registrations->added);

		$this->register(array('language' => '../Deutsch'));
		$this->assertSame('Deutsch', $this->registrations->added[0]->language(), 'a name stripped of its path characters is the pack it names');
	}

	public function testAnObserverReplacesTheAccountAndChangesTheLanguagesAndTheNotes(): void {
		$this->kit->events->observe(RegistrationStep::class, function (RegistrationStep $event): void {
			if ($event->step() === RegistrationStep::ADDING && $event->account() !== null)
			{
				$account = $event->account();
				$event->replaceAccount(new NewAccount('Anna', 5, $account->salt(), $account->password(), $account->passwordHash(), $account->email(), 2, 0.0, 0, 'English', 'Oxygen', 1, '192.0.2.7', null, false, true));
			}
		});
		$this->kit->events->observe(RegistrationRendering::class, function (RegistrationRendering $event): void {
			if ($event->position() === RegistrationRendering::OUTPUT_START)
				$event->set(RegistrationRendering::INFO, 'probe', '<p>probe</p>');

			if ($event->position() === RegistrationRendering::PRE_LANGUAGE)
				$event->offerLanguages(array('English'));
		});

		$body = $this->page();
		$this->assertStringContainsString("<div class=\"ct-box info-box\">\n\t\t\t<p>probe</p>\n\t\t</div>", $body);
		$this->assertStringNotContainsString('name="language"', $body);

		$this->register(array());
		$this->assertSame(array('Anna', 5, true), array($this->registrations->added[0]->username(), $this->registrations->added[0]->groupId(), $this->registrations->added[0]->notifiesAdmins()));
	}
}
