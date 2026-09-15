<?php

declare(strict_types=1);

namespace PunBB\Module\Register\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Chrome\PageScript;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Register\Api\Data\NewAccountInterface;
use PunBB\Module\Register\Api\RegistrationsInterface;
use PunBB\Module\Register\Creation\AccountCreationInterface;
use PunBB\Module\Register\Event\RegistrationAlerting;
use PunBB\Module\Register\Event\RegistrationRendering;
use PunBB\Module\Register\Event\RegistrationRequested;
use PunBB\Module\Register\Event\RegistrationStep;
use PunBB\Module\Register\Event\RulesRendering;
use PunBB\Module\Register\Model\NewAccount;
use PunBB\Module\Register\View\FormView;
use PunBB\Module\Site\Account\UsernameRulesInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Mail\MailerInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Security\PasswordsInterface;
use PunBB\Module\Site\Security\RandomKeysInterface;
use PunBB\Module\Site\Security\SignInInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * register.php: the forum rules a guest agrees to first when the board has
 * them, the registration form, and the account it registers. The board either
 * signs the new member in, or mails a key that verifies the account and a
 * password with it. A signed-in member is sent to the index.
 */
final class RegisterController implements ControllerInterface {
	private const RULES_TEMPLATE = __DIR__.'/../templates/rules.phtml';

	private const REGISTER_TEMPLATE = __DIR__.'/../templates/register.phtml';

	/** The group of an account no login has verified yet. */
	private const UNVERIFIED_GROUP = 0;

	/** How long the registrations from one address are counted to stop a flood of them. */
	private const FLOOD_WINDOW = 3600;

	/** How long an account may stay unverified before the next registration removes it. */
	private const UNVERIFIED_FOR = 259200;

	/** What a path must not carry, stripped from a language's name before it names a directory. */
	private const PATH_CHARACTERS = '#[\.\\\/]#';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly RegistrationsInterface $registrations,
		private readonly AccountCreationInterface $creation,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens,
		private readonly PasswordsInterface $passwords,
		private readonly RandomKeysInterface $keys,
		private readonly SignInInterface $signIn,
		private readonly UsernameRulesInterface $usernames,
		private readonly EmailAddressesInterface $emails,
		private readonly MailerInterface $mailer
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new RegistrationRequested());

		if (!$this->visitor->isGuest())
			return new Response('', 302, array('Location' => str_replace('&amp;', '&', $this->urls->link('index')->html)));

		$strings = $this->language->strings('profile');

		if ($this->settings->value('o_regs_allow') === '0')
			return $this->messages->respond(self::string($strings, 'No new regs'), json: $request->xhr);

		if (isset($request->query['cancel']) || (isset($request->query['agree']) && !isset($request->query['req_agreement'])))
			return $this->redirects->respond($this->urls->link('index')->html, self::string($strings, 'Reg cancel redirect'), $request->xhr);

		if ($this->settings->value('o_rules') === '1' && !isset($request->query['agree']) && !isset($request->post['form_sent']))
			return $this->rulesPage($strings);

		$errors = array();
		if (isset($request->post['form_sent']))
		{
			$registered = $this->register($request, $strings);
			if ($registered instanceof Response)
				return $registered;

			$errors = $registered;
		}

		return $this->registrationPage($request, $errors, $strings);
	}

	/**
	 * The account registered and the visitor signed in or told to verify it, or the errors that stopped it.
	 *
	 * @param array<string, Html> $strings
	 * @return Response|list<string>
	 */
	private function register(Request $request, array $strings): Response|array {
		$submitted = new RegistrationStep(RegistrationStep::SUBMITTED);
		$this->events->dispatch($submitted);
		$errors = $submitted->errors();

		if ($this->registrations->registrationsFrom($this->visitor->address(), time() - self::FLOOD_WINDOW) > 0)
			$errors[] = self::string($strings, 'Registration flood')->html;

		if ($errors !== array())
			return $errors;

		$username = self::text($request->post['req_username'] ?? null);
		$email = strtolower(self::text($request->post['req_email1'] ?? null));
		$verifies = $this->settings->value('o_regs_verify') === '1';

		if ($verifies)
			$password = $confirmation = $this->keys->key(8, readable: true);
		else
		{
			$password = self::text($request->post['req_password1'] ?? null);
			$confirmation = $this->settings->value('o_mask_passwords') === '1' ? self::text($request->post['req_password2'] ?? null) : $password;
		}

		foreach ($this->usernames->validate($username) as $error)
			$errors[] = $error->html;

		if (mb_strlen($password) < 4)
			$errors[] = self::string($strings, 'Pass too short')->html;
		else if ($password !== $confirmation)
			$errors[] = self::string($strings, 'Pass not match')->html;

		if (!$this->emails->isValid($email))
			$errors[] = self::string($strings, 'Invalid e-mail')->html;

		$banned = $this->emails->isBanned($email);
		if ($banned && $this->settings->value('p_allow_banned_email') === '0')
			$errors[] = self::string($strings, 'Banned e-mail')->html;

		$this->registrations->removeUnverified(time() - self::UNVERIFIED_FOR);

		$duplicates = $this->registrations->usernamesWithEmail($email);
		if ($duplicates !== array() && $errors === array() && $this->settings->value('p_allow_dupe_email') === '0')
			$errors[] = self::string($strings, 'Dupe e-mail')->html;

		$validated = new RegistrationStep(RegistrationStep::VALIDATED, $errors, $username, $email, $password);
		$this->events->dispatch($validated);
		$errors = $validated->errors();

		if ($errors !== array())
			return $errors;

		$language = $this->settings->value('o_default_lang');
		if (isset($request->post['language']))
		{
			if (!is_string($request->post['language']))
				return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

			// Only a language the board has a pack for, by a name that cannot leave lang/
			$language = (string) preg_replace(self::PATH_CHARACTERS, '', $request->post['language']);
			if (!in_array($language, $this->language->available(), true))
				return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
		}

		$timezone = isset($request->post['timezone']) ? self::decimal($request->post['timezone']) : (float) $this->settings->value('o_default_timezone');
		if ($timezone > 14.0 || $timezone < -12.0)
			$timezone = (float) $this->settings->value('o_default_timezone');

		$dst = isset($request->post['dst']) && self::integer($request->post['dst']) === 1 ? 1 : (int) $this->settings->value('o_default_dst');

		$proposed = new NewAccount(
			$username,
			$verifies ? self::UNVERIFIED_GROUP : (int) $this->settings->value('o_default_user_group'),
			$this->keys->key(12),
			$password,
			$this->passwords->hash($password),
			$email,
			(int) $this->settings->value('o_default_email_setting'),
			$timezone,
			$dst,
			$language,
			$this->settings->value('o_default_style'),
			time(),
			$this->visitor->address(),
			$verifies ? $this->keys->key(8, readable: true) : null,
			$verifies,
			$this->settings->value('o_regs_report') === '1'
		);

		$adding = new RegistrationStep(RegistrationStep::ADDING, account: $proposed);
		$this->events->dispatch($adding);

		$account = $adding->account() ?? $proposed;
		$userId = $this->creation->add($account);

		$mailingList = $this->settings->value('o_mailing_list');
		if ($mailingList !== '')
		{
			if ($banned)
				$this->alert(RegistrationAlerting::BANNED_EMAIL, $account, $userId, $duplicates, 'Alert - Banned e-mail detected',
					'User \''.$account->username().'\' registered with banned e-mail address: '.$account->email());

			if ($duplicates !== array())
				$this->alert(RegistrationAlerting::DUPLICATE_EMAIL, $account, $userId, $duplicates, 'Alert - Duplicate e-mail detected',
					'User \''.$account->username().'\' registered with an e-mail address that also belongs to: '.implode(', ', $duplicates));
		}

		$this->events->dispatch(new RegistrationStep(RegistrationStep::ADDED, account: $account, userId: $userId));

		if ($verifies)
		{
			$admin = $this->settings->value('o_admin_email');

			return $this->messages->respond(Html::format(self::string($strings, 'Reg e-mail'), Html::format('<a href="mailto:%s">%s</a>', $admin, $admin)), json: $request->xhr);
		}

		$this->signIn->signIn($userId, $account->passwordHash(), $account->salt(), time() + (int) $this->settings->value('o_timeout_visit'));

		return $this->redirects->respond($this->urls->link('index')->html, self::string($strings, 'Reg complete'), $request->xhr);
	}

	/**
	 * Tells the mailing list of a registration, as $alert names what it is about.
	 *
	 * @param list<string> $duplicates
	 */
	private function alert(string $alert, NewAccountInterface $account, int $userId, array $duplicates, string $subject, string $lead): void {
		$message = $lead."\n\n".'User profile: '.$this->urls->link('user', array($userId))->html."\n\n".'-- '."\n".'Forum Mailer'."\n".'(Do not reply to this message)';

		$alerting = new RegistrationAlerting($alert, $account, $userId, $duplicates, $subject, $message);
		$this->events->dispatch($alerting);

		$this->mailer->send($this->settings->value('o_mailing_list'), $alerting->subject(), $alerting->message());
	}

	/** @param array<string, Html> $strings */
	private function rulesPage(array $strings): Response {
		$head = new PageHead('rules-register', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb($this->language->text('common', 'Register')->html, $this->urls->link('register')),
			new Crumb($this->language->text('common', 'Rules')->html),
		));

		return $this->pages->respond($head, fn (): array => array('main' => $this->rules($strings)));
	}

	/** @param array<string, Html> $strings */
	private function rules(array $strings): Html {
		$view = new FormView(RulesRendering::POSITIONS, array(
			'profile'	=> $strings,
			'common'	=> $this->language->strings('common'),
			'heading'	=> Html::format(self::string($strings, 'Register at'), $this->settings->value('o_board_title')),
			'rules'		=> new Html($this->settings->value('o_rules_message')),
			'action'	=> $this->urls->link('register'),
		));

		$start = $this->rulesAt($view, RulesRendering::OUTPUT_START);
		$view->restartFields();

		$this->rulesAt($view, RulesRendering::PRE_GROUP);
		$view->numberGroup('group');

		$this->rulesAt($view, RulesRendering::PRE_AGREE_CHECKBOX);
		$view->numberItem('agree_item');
		$view->numberField('agree_field');

		$this->rulesAt($view, RulesRendering::PRE_GROUP_END);
		$this->rulesAt($view, RulesRendering::GROUP_END);

		$body = $this->templates->render(self::RULES_TEMPLATE, $view->variables());

		$end = new RulesRendering(RulesRendering::END, ...$view->counts());
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	private function rulesAt(FormView $view, string $position): RulesRendering {
		$event = new RulesRendering($position, ...$view->counts());
		$this->events->dispatch($event);
		$view->place($event);

		return $event;
	}

	/**
	 * @param list<string> $errors
	 * @param array<string, Html> $strings
	 */
	private function registrationPage(Request $request, array $errors, array $strings): Response {
		$head = new PageHead('register', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(sprintf(self::string($strings, 'Register at')->html, $this->settings->value('o_board_title'))),
		), scripts: array(
			new PageScript($this->urls->base().'/include/js/punbb.timezone.js'),
			new PageScript('PUNBB.timezone.detect_on_register_form();', inline: true),
		));

		return $this->pages->respond($head, fn (): array => array('main' => $this->registrationForm($request, $errors, $strings)));
	}

	/**
	 * @param list<string> $errors
	 * @param array<string, Html> $strings
	 */
	private function registrationForm(Request $request, array $errors, array $strings): Html {
		$action = new Html($this->urls->link('register')->html.'?action=register');
		$verifies = $this->settings->value('o_regs_verify') !== '0';

		$view = new FormView(RegistrationRendering::POSITIONS, array(
			'profile'		=> $strings,
			'common'		=> $this->language->strings('common'),
			'heading'		=> Html::format(self::string($strings, 'Register at'), $this->settings->value('o_board_title')),
			'action'		=> $action,
			'token'			=> $this->tokens->token($action->html),
			'timezone'		=> $this->settings->value('o_default_timezone'),
			'dst'			=> $this->settings->value('o_default_dst'),
			'verifies'		=> $verifies,
			'masks'			=> $this->settings->value('o_mask_passwords') === '1',
			'email'			=> self::submitted($request->post['req_email1'] ?? null),
			'username'		=> self::submitted($request->post['req_username'] ?? null),
			'password'		=> self::submitted($request->post['req_password1'] ?? null),
			'confirmation'	=> self::submitted($request->post['req_password2'] ?? null),
		));

		$info = new Parts();
		if ($verifies)
			$info->set('email', Html::format('<p class="warn">%s</p>', self::string($strings, 'Reg e-mail info'))->html);

		$start = $this->registrationAt($view, $action, RegistrationRendering::OUTPUT_START, $info);
		$view->show('info', $start->names(RegistrationRendering::INFO) !== array() ? self::joined($start, RegistrationRendering::INFO, "\n\t\t\t") : null);

		$listed = null;
		if ($errors !== array())
		{
			$parts = new Parts();
			foreach ($errors as $number => $error)
				$parts->set((string) $number, '<li class="warn"><span>'.$error.'</span></li>');

			$listed = self::joined($this->registrationAt($view, $action, RegistrationRendering::PRE_REGISTER_ERRORS, errors: $parts), RegistrationRendering::ERRORS, "\n\t\t\t\t");
		}

		$view->show('errors', $listed);

		$this->registrationAt($view, $action, RegistrationRendering::PRE_GROUP);
		$view->numberGroup('group');

		$this->registrationAt($view, $action, RegistrationRendering::PRE_EMAIL);
		$view->numberItem('email_item');
		$view->numberField('email_field');

		$this->registrationAt($view, $action, RegistrationRendering::PRE_USERNAME);
		$view->numberItem('username_item');
		$view->numberField('username_field');

		$this->registrationAt($view, $action, RegistrationRendering::PRE_PASSWORD);
		if (!$verifies)
		{
			$view->numberItem('password_item');
			$view->numberField('password_field');

			$this->registrationAt($view, $action, RegistrationRendering::PRE_CONFIRM_PASSWORD);
			if ($this->settings->value('o_mask_passwords') === '1')
			{
				$view->numberItem('confirm_item');
				$view->numberField('confirm_field');
			}
		}

		$this->registrationAt($view, $action, RegistrationRendering::PRE_EMAIL_CONFIRM);

		$offered = $this->registrationAt($view, $action, RegistrationRendering::PRE_LANGUAGE, languages: $this->language->available())->languages();

		$languages = array();
		if (count($offered) > 1)
		{
			natcasesort($offered);

			$view->numberItem('language_item');
			$view->numberField('language_field');

			$chosen = $request->post['language'] ?? $this->settings->value('o_default_lang');
			foreach ($offered as $name)
				$languages[] = array('name' => $name, 'selected' => new Html($chosen === $name ? ' selected="selected"' : ''));
		}

		$view->show('languages', $languages);

		$this->registrationAt($view, $action, RegistrationRendering::PRE_GROUP_END);
		$this->registrationAt($view, $action, RegistrationRendering::GROUP_END);

		$body = $this->templates->render(self::REGISTER_TEMPLATE, $view->variables());

		$end = new RegistrationRendering(RegistrationRendering::END, $action->html, ...$view->counts());
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	/** @param list<string> $languages */
	private function registrationAt(FormView $view, Html $action, string $position, ?Parts $info = null, ?Parts $errors = null, array $languages = array()): RegistrationRendering {
		$event = new RegistrationRendering($position, $action->html, ...array_merge($view->counts(), array($info, $errors, $languages)));
		$this->events->dispatch($event);
		$view->place($event);

		return $event;
	}

	/** The parts of $group, joined with $glue. */
	private static function joined(RegistrationRendering $event, string $group, string $glue): Html {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[] = (string) $event->entry($group, $name);

		return new Html(implode($glue, $parts));
	}

	/** A value the request carries as text, trimmed; anything else is empty. */
	private static function text(mixed $value): string {
		return is_string($value) ? (new Html($value))->trim()->html : '';
	}

	/** What the visitor typed into a field, shown back as they typed it; nothing for a value that is no text. */
	private static function submitted(mixed $value): string {
		return is_string($value) ? $value : '';
	}

	/** A value the request carries, as intval() took it. */
	private static function integer(mixed $value): int {
		return is_scalar($value) ? intval($value) : (int) ($value !== array() && $value !== null);
	}

	/** A value the request carries, as floatval() took it. */
	private static function decimal(mixed $value): float {
		return is_scalar($value) ? floatval($value) : (float) ($value !== array() && $value !== null);
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
