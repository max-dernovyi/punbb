<?php

declare(strict_types=1);

namespace PunBB\Module\Login;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Login\Api\AccountsInterface;
use PunBB\Module\Login\Api\VisitsInterface;
use PunBB\Module\Login\Controller\LoginController;
use PunBB\Module\Login\Interceptor\AccountsInterceptor;
use PunBB\Module\Login\Interceptor\VisitsInterceptor;
use PunBB\Module\Login\Model\Accounts;
use PunBB\Module\Login\Model\Visits;
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
 * Signing in and out, and asking for a new password. A request for an action
 * leaves no visit behind.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Login';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '2.0.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(AccountsInterface::class, AccountsInterceptor::class, fn (Container $c): object => new Accounts($c->get(Connection::class)));
		$wiring->contract(VisitsInterface::class, VisitsInterceptor::class, fn (Container $c): object => new Visits($c->get(Connection::class)));

		$wiring->route(array('login.php'), LoginController::class, fn (Container $c): object => new LoginController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ConfirmPage::class),
			$c->get(AccountsInterface::class),
			$c->get(VisitsInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(PasswordsInterface::class),
			$c->get(RandomKeysInterface::class),
			$c->get(SignInInterface::class),
			$c->get(EmailAddressesInterface::class),
			$c->get(MailerInterface::class),
			$c->get(DeferredWorkInterface::class)
		), quietWith: array('action'));
	}
}
