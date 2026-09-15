<?php
/**
 * What the login page reads and writes, over plain properties: the accounts
 * and visits, the password checks, the keys, the cookie, the mail and the
 * work left for after the response.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Login\Api\AccountsInterface;
use PunBB\Module\Login\Api\Data\CredentialsInterface;
use PunBB\Module\Login\Api\Data\LastVisitInterface;
use PunBB\Module\Login\Api\Data\ResetKeyInterface;
use PunBB\Module\Login\Api\VisitsInterface;
use PunBB\Module\Login\Controller\LoginController;
use PunBB\Module\Login\Event\LoginRendering;
use PunBB\Module\Login\Event\LoginRequested;
use PunBB\Module\Login\Event\LoginStep;
use PunBB\Module\Login\Event\LogoutStep;
use PunBB\Module\Login\Event\PasswordRequestRendering;
use PunBB\Module\Login\Event\PasswordRequestStep;
use PunBB\Module\Login\Event\PasswordResetMailing;
use PunBB\Module\Login\Model\Credentials;
use PunBB\Module\Login\Model\ResettableAccount;
use PunBB\Module\Message\Event\ConfirmFormRendering;
use PunBB\Module\Message\Event\ConfirmFormRequested;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Mail\MailerInterface;
use PunBB\Module\Site\Security\PasswordsInterface;
use PunBB\Module\Site\Security\RandomKeysInterface;
use PunBB\Module\Site\Security\SignInInterface;
use PunBB\Module\Site\Work\DeferredWorkInterface;

require_once __DIR__.'/PageFakes.php';

/** The password "right" matches the hash "hash:right"; a hash starting "old:" is in an older format. */
final class FakeLoginServices implements AccountsInterface, VisitsInterface, PasswordsInterface, RandomKeysInterface, SignInInterface, EmailAddressesInterface, MailerInterface, DeferredWorkInterface {
	/** @var array<string, Credentials> username => credentials */
	public array $credentials = array();

	/** @var array<string, list<ResettableAccount>> email => its accounts */
	public array $resettable = array();

	/** @var list<string> what was read, checked, stored and sent, in order */
	public array $log = array();

	/** @var list<array{string, string, string, bool}> to, subject, message, quiet */
	public array $mail = array();

	/** @var list<Closure(): void> */
	public array $deferred = array();

	public function credentials(string $username): ?CredentialsInterface {
		$this->log[] = 'credentials '.$username;

		return $this->credentials[$username] ?? null;
	}

	public function storePassword(CredentialsInterface ...$credentials): void {
		foreach ($credentials as $credential)
			$this->log[] = 'store '.$credential->userId().' '.$credential->passwordHash().' '.$credential->salt();
	}

	public function activate(int $groupId, int ...$userIds): void {
		$this->log[] = 'activate '.implode(',', $userIds).' into '.$groupId;
	}

	public function recordLastVisit(LastVisitInterface ...$visits): void {
		foreach ($visits as $visit)
			$this->log[] = 'last visit '.$visit->userId().' at '.$visit->at();
	}

	public function resettable(string $email): array {
		$this->log[] = 'resettable '.$email;

		return $this->resettable[$email] ?? array();
	}

	public function issueResetKey(ResetKeyInterface ...$keys): void {
		foreach ($keys as $key)
			$this->log[] = 'reset key '.$key->userId().' '.$key->key();
	}

	public function endGuestVisit(string ...$addresses): void {
		$this->log[] = 'end guest visit '.implode(',', $addresses);
	}

	public function endVisit(int ...$userIds): void {
		$this->log[] = 'end visit '.implode(',', $userIds);
	}

	public function hash(string $password): string {
		return 'hash:'.$password;
	}

	public function verify(string $password, string $hash, string $salt): bool {
		$this->log[] = 'verify';

		return $hash === 'hash:'.$password || $hash === 'old:'.$password;
	}

	public string $visitorPassword = '';

	public function verifyVisitor(string $password): bool {
		return $password === $this->visitorPassword;
	}

	public function verifyAgainstNobody(string $password): void {
		$this->log[] = 'verify against nobody';
	}

	public function needsRehash(string $hash): bool {
		return str_starts_with($hash, 'old:');
	}

	public function resetKeyLifetime(): int {
		return 3600;
	}

	public function key(int $length, bool $readable = false, bool $hash = false): string {
		return 'key'.$length.($readable ? 'r' : '').($hash ? 'h' : '');
	}

	public function regenerateSession(): void {
		$this->log[] = 'regenerate session';
	}

	public function signIn(int $userId, string $passwordHash, string $salt, int $expire): void {
		$this->log[] = 'sign in '.$userId.' '.$passwordHash.' '.$salt.' for '.($expire - time());
	}

	public int $cookieExpiry = 0;

	public function expiryOf(array $cookies): int {
		return $this->cookieExpiry;
	}

	public function signOut(): void {
		$this->log[] = 'sign out';
	}

	public function isValid(string $address): bool {
		return str_contains($address, '@');
	}

	public function isBanned(string $address): bool {
		return str_ends_with($address, '@banned.invalid');
	}

	public function send(string $to, string $subject, string $message, bool $quiet = false, string $replyTo = '', string $replyToName = ''): void {
		$this->mail[] = array($to, $subject, $message, $quiet);
	}

	public function defer(Closure $work): void {
		$this->deferred[] = $work;
	}

	public function runDeferred(): void {
		foreach ($this->deferred as $work)
			$work();

		$this->deferred = array();
	}
}

/** The login page over the fakes, asked as a visitor would ask it. */
final class LoginKit {
	public const EVENTS = array(LoginRequested::class, LoginStep::class, LogoutStep::class, PasswordRequestStep::class, PasswordResetMailing::class, LoginRendering::class, PasswordRequestRendering::class,
		MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class, ConfirmFormRequested::class, ConfirmFormRendering::class);

	public PageKit $kit;

	public FakeLoginServices $services;

	public function __construct() {
		$this->kit = new PageKit(self::EVENTS);
		$this->kit->language->real = array('common', 'login');
		$this->kit->language->mailTemplates['activate_password'] = "Subject: New password requested\n\nHello <username>,\n\nVisit <activation_url> at <base_url>.\n\n--\n<board_mailer>";
		$this->kit->settings->values += array('o_redirect_delay' => '0', 'o_admin_email' => 'admin@example.com', 'o_default_user_group' => '4', 'o_timeout_visit' => '1800');
		$this->kit->visitor->guest = true;
		$this->kit->visitor->id = 1;
		$this->services = new FakeLoginServices();
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 */
	public function page(array $query = array(), array $post = array()): string {
		$confirmations = new ConfirmPage($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->redirects(), $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->visitor, $this->kit->tokens);
		$controller = new LoginController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(), $confirmations,
			$this->services, $this->services, $this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->tokens,
			$this->services, $this->services, $this->services, $this->services, $this->services, $this->services);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'login.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}
}
