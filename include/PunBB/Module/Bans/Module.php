<?php

declare(strict_types=1);

namespace PunBB\Module\Bans;

use PunBB\Module\Bans\Api\BanCandidatesInterface;
use PunBB\Module\Bans\Api\BansInterface;
use PunBB\Module\Bans\Controller\BansController;
use PunBB\Module\Bans\Interceptor\BanCandidatesInterceptor;
use PunBB\Module\Bans\Interceptor\BansInterceptor;
use PunBB\Module\Bans\Model\BanCandidates;
use PunBB\Module\Bans\Model\Bans;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Site\Cache\BanCacheInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The bans: listing, adding, editing and removing them. The list every request
 * is checked against is Site\Cache\BanCacheInterface.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Bans';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(BansInterface::class, BansInterceptor::class, fn (Container $c): object => new Bans($c->get(Connection::class)));
		$wiring->contract(BanCandidatesInterface::class, BanCandidatesInterceptor::class, fn (Container $c): object => new BanCandidates($c->get(Connection::class)));

		$wiring->route(array('admin/bans.php'), BansController::class, fn (Container $c): object => new BansController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ConfirmPage::class),
			$c->get(BansInterface::class),
			$c->get(BanCandidatesInterface::class),
			$c->get(BanCacheInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(FlashMessagesInterface::class),
			$c->get(EmailAddressesInterface::class)
		));
	}
}
