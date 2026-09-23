<?php

declare(strict_types=1);

namespace PunBB\Module\Register;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Register\Api\RegistrationsInterface;
use PunBB\Module\Register\Controller\RegisterController;
use PunBB\Module\Register\Creation\AccountCreationInterface;
use PunBB\Module\Register\Interceptor\RegistrationsInterceptor;
use PunBB\Module\Register\Model\Registrations;
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
 * Registering an account. Storing it, with the mail that verifies it, is
 * AccountCreationInterface, which the bootstrap's side wires.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Register';
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
		$wiring->contract(RegistrationsInterface::class, RegistrationsInterceptor::class, fn (Container $c): object => new Registrations($c->get(Connection::class)));

		$wiring->route(array('register.php'), RegisterController::class, fn (Container $c): object => new RegisterController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(RegistrationsInterface::class),
			$c->get(AccountCreationInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(PasswordsInterface::class),
			$c->get(RandomKeysInterface::class),
			$c->get(SignInInterface::class),
			$c->get(UsernameRulesInterface::class),
			$c->get(EmailAddressesInterface::class),
			$c->get(MailerInterface::class)
		));
	}
}
