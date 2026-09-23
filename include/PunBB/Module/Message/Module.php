<?php

declare(strict_types=1);

namespace PunBB\Module\Message;

use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Chrome\ChromeFactoryInterface;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MaintenancePage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The pages a request is answered with when it is not served a page of its
 * own: a message, a redirect, the confirmation of a form whose token did not
 * match, and the maintenance message.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Message';
	}

	public function dependencies(): array {
		return array('Framework', 'Layout', 'Site');
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '2.0.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->service(MessagePage::class, fn (Container $c): object => new MessagePage(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class)
		));

		$wiring->service(RedirectPage::class, fn (Container $c): object => new RedirectPage(
			$c->get(EventDispatcher::class),
			$c->get(ChromeFactoryInterface::class),
			$c->get(TemplateRenderer::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class)
		));

		$wiring->service(ConfirmPage::class, fn (Container $c): object => new ConfirmPage(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(RedirectPage::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(CsrfTokensInterface::class)
		));

		$wiring->service(MaintenancePage::class, fn (Container $c): object => new MaintenancePage(
			$c->get(EventDispatcher::class),
			$c->get(ChromeFactoryInterface::class),
			$c->get(TemplateRenderer::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class)
		));
	}
}
