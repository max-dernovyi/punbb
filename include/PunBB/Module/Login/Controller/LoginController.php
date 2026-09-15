<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Login\Api\AccountsInterface;
use PunBB\Module\Login\Api\Data\ResettableAccountInterface;
use PunBB\Module\Login\Api\VisitsInterface;
use PunBB\Module\Login\Event\LoginRendering;
use PunBB\Module\Login\Event\LoginRequested;
use PunBB\Module\Login\Event\LoginStep;
use PunBB\Module\Login\Event\LogoutStep;
use PunBB\Module\Login\Event\PasswordRequestRendering;
use PunBB\Module\Login\Event\PasswordRequestStep;
use PunBB\Module\Login\Event\PasswordResetMailing;
use PunBB\Module\Login\Model\Credentials;
use PunBB\Module\Login\Model\LastVisit;
use PunBB\Module\Login\Model\ResetKey;
use PunBB\Module\Login\View\FormView;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
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
use PunBB\Module\Site\Work\DeferredWorkInterface;

/**
 * login.php: signing in, signing out by the member's own link, and asking for
 * a new password. A signed-in member asking for the login form or a new
 * password is sent to the index. Neither unauthenticated form tells the
 * visitor which accounts exist: a login costs the same without one, and a
 * password request answers the same for every address, mailing the keys only
 * once the answer is delivered.
 */
final class LoginController implements ControllerInterface {
	private const LOGIN_TEMPLATE = __DIR__.'/../templates/login.phtml';

	private const REQUEST_TEMPLATE = __DIR__.'/../templates/request.phtml';

	/** The group of an account no login has verified yet. */
	private const UNVERIFIED_GROUP = 0;

	private const ADMINISTRATORS_GROUP = 1;

	/** How long a login the visitor asked to remember lasts: two weeks. */
	private const REMEMBERED_FOR = 1209600;

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ConfirmPage $confirmations,
		private readonly AccountsInterface $accounts,
		private readonly VisitsInterface $visits,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens,
		private readonly PasswordsInterface $passwords,
		private readonly RandomKeysInterface $keys,
		private readonly SignInInterface $signIn,
		private readonly EmailAddressesInterface $emails,
		private readonly MailerInterface $mailer,
		private readonly DeferredWorkInterface $work
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new LoginRequested());

		$strings = $this->language->strings('login');

		$action = $request->query['action'] ?? null;
		$errors = array();

		if (isset($request->post['form_sent']) && empty($action))
		{
			$signedIn = $this->signIn($request, $strings);
			if ($signedIn instanceof Response)
				return $signedIn;

			$errors = $signedIn;
		}
		else if ($action === 'out')
			return $this->signOut($request, $strings);
		else if ($action === 'forget' || $action === 'forget_2')
			return $this->requestPassword($request, $strings);

		if (!$this->visitor->isGuest())
			return $this->home();

		return $this->loginPage($request, $errors, $strings);
	}

	/**
	 * The visitor signed in and sent on, or the errors that stopped them.
	 *
	 * @param array<string, Html> $strings
	 * @return Response|list<string>
	 */
	private function signIn(Request $request, array $strings): Response|array {
		$username = self::text($request->post['req_username'] ?? null);
		$password = self::text($request->post['req_password'] ?? null);
		$remember = isset($request->post['save_pass']);

		$submitted = new LoginStep(LoginStep::SUBMITTED, $username, $remember);
		$this->events->dispatch($submitted);

		$credentials = $this->accounts->credentials($username);

		$authorized = false;
		if ($credentials === null || $credentials->passwordHash() === '')
			$this->passwords->verifyAgainstNobody($password);
		else if ($this->passwords->verify($password, $credentials->passwordHash(), $credentials->salt()))
		{
			$authorized = true;

			// The plaintext is at hand exactly once: the moment an older hash is stored again. The salt stays, the cookie is built from it.
			if ($this->passwords->needsRehash($credentials->passwordHash()))
			{
				$credentials = new Credentials($credentials->userId(), $credentials->groupId(), $this->passwords->hash($password),
					$credentials->salt() !== '' ? $credentials->salt() : $this->keys->key(12));

				$this->accounts->storePassword($credentials);
			}
		}

		$checked = new LoginStep(LoginStep::CHECKED, $username, $remember, $credentials, $authorized, $submitted->errors());
		$this->events->dispatch($checked);

		$errors = $checked->errors();
		if (!$checked->authorized())
			$errors[] = self::string($strings, 'Wrong user/pass')->html;

		if ($errors !== array() || $credentials === null)
			return $errors;

		if ($credentials->groupId() === self::UNVERIFIED_GROUP)
			$this->accounts->activate((int) $this->settings->value('o_default_user_group'), $credentials->userId());

		$this->visits->endGuestVisit($this->visitor->address());

		// Who the request is changes here: nothing planted under the old session id survives it
		$this->signIn->regenerateSession();

		$expire = time() + ($remember ? self::REMEMBERED_FOR : (int) $this->settings->value('o_timeout_visit'));
		$this->signIn->signIn($credentials->userId(), $credentials->passwordHash(), $credentials->salt(), $expire);

		$this->events->dispatch(new LoginStep(LoginStep::SIGNED_IN, $username, $remember, $credentials, true));

		$back = is_string($request->post['redirect_url'] ?? null) ? $request->post['redirect_url'] : '';

		return $this->redirects->respond(Html::escape($back)->html.(substr_count($back, '?') === 1 ? '&amp;' : '?').'login=1', self::string($strings, 'Login redirect'), $request->xhr);
	}

	/**
	 * The member signed out by their own link, once its token checks out or they confirm.
	 *
	 * @param array<string, Html> $strings
	 */
	private function signOut(Request $request, array $strings): Response {
		$id = $request->query['id'] ?? null;
		if ($this->visitor->isGuest() || !is_scalar($id) || (string) $id != (string) $this->visitor->id())
			return $this->home();

		// A token posted has passed the gate every POST goes through; one in the link is checked here
		if (!isset($request->post['csrf_token']) && !$this->tokens->matches($request->query['csrf_token'] ?? null, 'logout'.$this->visitor->id()))
		{
			$confirmation = $this->confirmations->respond($request->post, $request->xhr);
			if ($confirmation !== null)
				return $confirmation;
		}

		$userId = $this->visitor->id();
		$this->events->dispatch(new LogoutStep(LogoutStep::SELECTED, $userId));

		$this->visits->endVisit($userId);

		$logged = $this->visitor->loggedAt();
		if ($logged !== null)
			$this->accounts->recordLastVisit(new LastVisit($userId, $logged));

		$this->signIn->regenerateSession();
		$this->signIn->signOut();
		$this->visitor->forgetTrackedTopics();

		$this->events->dispatch(new LogoutStep(LogoutStep::SIGNED_OUT, $userId));

		return $this->redirects->respond($this->urls->link('index')->html, self::string($strings, 'Logout redirect'), $request->xhr);
	}

	/**
	 * The form asking for a new password, and the one answer every address gets.
	 *
	 * @param array<string, Html> $strings
	 */
	private function requestPassword(Request $request, array $strings): Response {
		if (!$this->visitor->isGuest())
			return $this->home();

		$this->events->dispatch(new PasswordRequestStep(PasswordRequestStep::SELECTED));

		$errors = array();
		if (isset($request->post['form_sent']))
		{
			if (isset($request->post['cancel']))
				return $this->redirects->respond($this->urls->link('index')->html, self::string($strings, 'New password cancel redirect'), $request->xhr);

			$email = strtolower(self::text($request->post['req_email'] ?? null));
			if (!$this->emails->isValid($email))
				$errors[] = self::string($strings, 'Invalid e-mail')->html;

			$validated = new PasswordRequestStep(PasswordRequestStep::VALIDATED, $email, $errors);
			$this->events->dispatch($validated);
			$errors = $validated->errors();

			if ($errors === array())
			{
				$accounts = $this->accounts->resettable($email);

				// Whatever the address turns out to be, the work that differs runs after the answer is delivered
				$this->work->defer(function () use ($email, $accounts): void {
					$this->mailResetKeys($email, $accounts);
				});

				$admin = $this->settings->value('o_admin_email');

				return $this->messages->respond(Html::format(self::string($strings, 'Forget mail'), Html::format('<a href="mailto:%s">%s</a>', $admin, $admin)), json: $request->xhr);
			}
		}

		return $this->requestPage($request, $errors, $strings);
	}

	/**
	 * Mails each account of $email a key its password is reset with. An
	 * administrator's account, and one mailed a key that has not expired yet,
	 * is skipped without a word: the visitor already has their answer.
	 *
	 * @param list<ResettableAccountInterface> $accounts
	 */
	private function mailResetKeys(string $email, array $accounts): void {
		if ($accounts === array())
			return;

		$this->events->dispatch(new PasswordResetMailing(PasswordResetMailing::STARTING, $email, $accounts));

		$template = (new Html($this->language->mailTemplate('activate_password')))->trim()->html;
		if ($template === '')
		{
			error_log('PunBB: the activate_password mail template for language "'.$this->visitor->language().'" is missing or empty');
			return;
		}

		// The first line is the subject
		$firstLine = (int) strpos($template, "\n");
		$message = (new Html(substr($template, $firstLine)))->trim()->html;
		$message = str_replace('<base_url>', $this->urls->base().'/', $message);
		$message = str_replace('<board_mailer>', sprintf($this->language->text('common', 'Forum mailer')->html, $this->settings->value('o_board_title')), $message);

		$composed = new PasswordResetMailing(PasswordResetMailing::COMPOSED, $email, $accounts, (new Html(substr($template, 8, $firstLine - 8)))->trim()->html, $message);
		$this->events->dispatch($composed);

		foreach ($accounts as $account)
		{
			$checking = new PasswordResetMailing(PasswordResetMailing::CHECKING, $email, $accounts, $composed->subject(), $composed->message(), $account, $this->passwords->resetKeyLifetime());
			$this->events->dispatch($checking);

			if ($account->groupId() === self::ADMINISTRATORS_GROUP)
				continue;

			$sent = $account->lastEmailSent();
			if ($sent !== null && time() - $sent < $checking->keyLifetime() && time() - $sent >= 0)
				continue;

			$key = new ResetKey($account->id(), $this->keys->key(8, readable: true), time());
			$this->accounts->issueResetKey($key);

			$message = str_replace('<username>', $account->username(), $composed->message());
			$message = str_replace('<activation_url>', str_replace('&amp;', '&', $this->urls->link('change_password_key', array($account->id(), $key->key()))->html), $message);

			$addressed = new PasswordResetMailing(PasswordResetMailing::ADDRESSED, $email, $accounts, $composed->subject(), $message, $account, $checking->keyLifetime(), $key->key());
			$this->events->dispatch($addressed);

			// Quiet: an error page from a relay would name the address as one with an account
			$this->mailer->send($email, $composed->subject(), $addressed->message(), true);
		}
	}

	/**
	 * @param list<string> $errors
	 * @param array<string, Html> $strings
	 */
	private function loginPage(Request $request, array $errors, array $strings): Response {
		$heading = sprintf(self::string($strings, 'Login info')->html, $this->settings->value('o_board_title'));

		$head = new PageHead('login', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb($heading),
		));

		return $this->pages->respond($head, fn (): array => array('main' => $this->loginForm($request, $errors, $strings)));
	}

	/**
	 * @param list<string> $errors
	 * @param array<string, Html> $strings
	 */
	private function loginForm(Request $request, array $errors, array $strings): Html {
		$action = $this->urls->link('login');

		$view = new FormView(LoginRendering::POSITIONS, array(
			'login'		=> $strings,
			'common'	=> $this->language->strings('common'),
			'action'	=> $action,
			'heading'	=> Html::format(self::string($strings, 'Login info'), $this->settings->value('o_board_title')),
			'options'	=> Html::format(self::string($strings, 'Login options'),
				Html::format('<a href="%s">%s</a>', $this->urls->link('register'), self::string($strings, 'register')),
				Html::format('<a href="%s">%s</a>', $this->urls->link('request_password'), self::string($strings, 'Obtain pass'))),
			'username'	=> self::submitted($request->post['req_username'] ?? null),
			'password'	=> self::submitted($request->post['req_password'] ?? null),
			'remember'	=> new Html(isset($request->post['save_pass']) ? ' checked="checked"' : ''),
		));

		$start = new LoginRendering(LoginRendering::OUTPUT_START, $action->html, ...array_merge($view->counts(), array(new Parts(array(
			'form_sent'		=> '<input type="hidden" name="form_sent" value="1" />',
			'redirect_url'	=> Html::format('<input type="hidden" name="redirect_url" value="%s" />', $this->visitor->previousUrl())->html,
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $this->tokens->token($action->html))->html,
		)))));
		$this->events->dispatch($start);
		$view->place($start);
		$view->show('hidden', self::joined($start, LoginRendering::HIDDEN_FIELDS, "\n\t\t\t\t"));

		$listed = null;
		if ($errors !== array())
		{
			$parts = new Parts();
			foreach ($errors as $number => $error)
				$parts->set((string) $number, '<li class="warn"><span>'.$error.'</span></li>');

			$event = new LoginRendering(LoginRendering::PRE_LOGIN_ERRORS, $action->html, ...array_merge($view->counts(), array(null, $parts)));
			$this->events->dispatch($event);
			$view->place($event);
			$listed = self::joined($event, LoginRendering::ERRORS, "\n\t\t\t\t");
		}

		$view->show('errors', $listed);

		$at = function (string $position) use ($view, $action): void {
			$event = new LoginRendering($position, $action->html, ...$view->counts());
			$this->events->dispatch($event);
			$view->place($event);
		};

		$at(LoginRendering::PRE_LOGIN_GROUP);
		$view->numberGroup('group');

		$at(LoginRendering::PRE_USERNAME);
		$view->numberItem('username_item');
		$view->numberField('username_field');

		$at(LoginRendering::PRE_PASS);
		$view->numberItem('password_item');
		$view->numberField('password_field');

		$at(LoginRendering::PRE_REMEMBER_ME_CHECKBOX);
		$view->numberItem('remember_item');
		$view->numberField('remember_field');

		$at(LoginRendering::PRE_GROUP_END);
		$at(LoginRendering::GROUP_END);

		$body = $this->templates->render(self::LOGIN_TEMPLATE, $view->variables());

		$end = new LoginRendering(LoginRendering::END, $action->html, ...$view->counts());
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	/**
	 * @param list<string> $errors
	 * @param array<string, Html> $strings
	 */
	private function requestPage(Request $request, array $errors, array $strings): Response {
		$head = new PageHead('reqpass', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($strings, 'New password request')->html),
		));

		return $this->pages->respond($head, fn (): array => array('main' => $this->requestForm($request, $errors, $strings)));
	}

	/**
	 * @param list<string> $errors
	 * @param array<string, Html> $strings
	 */
	private function requestForm(Request $request, array $errors, array $strings): Html {
		$action = $this->urls->link('request_password');

		$view = new FormView(PasswordRequestRendering::POSITIONS, array(
			'login'		=> $strings,
			'common'	=> $this->language->strings('common'),
			'action'	=> $action,
			'token'		=> $this->tokens->token($action->html),
			'email'		=> self::submitted($request->post['req_email'] ?? null),
		));

		$start = $this->requestAt($view, $action, PasswordRequestRendering::OUTPUT_START);

		$listed = null;
		if ($errors !== array())
		{
			$event = $this->requestAt($view, $action, PasswordRequestRendering::PRE_NEW_PASSWORD_ERRORS, $errors);

			$lines = array();
			foreach ($event->names() as $name)
				$lines[] = (string) $event->entry($name);

			$listed = new Html(implode("\n\t\t\t\t", $lines));
		}

		$view->show('errors', $listed);

		$this->requestAt($view, $action, PasswordRequestRendering::PRE_GROUP);
		$view->numberGroup('group');

		$this->requestAt($view, $action, PasswordRequestRendering::PRE_EMAIL);
		$view->numberItem('email_item');
		$view->numberField('email_field');

		$this->requestAt($view, $action, PasswordRequestRendering::PRE_GROUP_END);
		$this->requestAt($view, $action, PasswordRequestRendering::GROUP_END);

		$body = $this->templates->render(self::REQUEST_TEMPLATE, $view->variables());

		$end = new PasswordRequestRendering(PasswordRequestRendering::END, $action->html, ...$view->counts());
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	/** @param list<string> $errors each placed in the list of errors */
	private function requestAt(FormView $view, Html $action, string $position, array $errors = array()): PasswordRequestRendering {
		$event = new PasswordRequestRendering($position, $action->html, ...$view->counts());
		foreach ($errors as $number => $error)
			$event->set((string) $number, '<li class="warn"><span>'.$error.'</span></li>');

		$this->events->dispatch($event);
		$view->place($event);

		return $event;
	}

	/** Sends the browser to the index, as the page did with a bare Location header. */
	private function home(): Response {
		return new Response('', 302, array('Location' => str_replace('&amp;', '&', $this->urls->link('index')->html)));
	}

	/** The parts of $group, joined with $glue. */
	private static function joined(LoginRendering $event, string $group, string $glue): Html {
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

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
