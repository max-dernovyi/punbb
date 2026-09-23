<?php

declare(strict_types=1);

namespace PunBB\Module\Extern;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Extern\Api\SyndicationInterface;
use PunBB\Module\Extern\Authentication\BasicAuthenticationInterface;
use PunBB\Module\Extern\Controller\ExternController;
use PunBB\Module\Extern\Interceptor\SyndicationInterceptor;
use PunBB\Module\Extern\Model\Syndication;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * Syndication: the board's recent topics and posts as feeds, who is online and
 * its statistics, for other sites. A feed reader's request is no visit.
 * Signing a reader in is BasicAuthenticationInterface, which the bootstrap's side wires.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Extern';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site');
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '2.0.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(SyndicationInterface::class, SyndicationInterceptor::class, fn (Container $c): object => new Syndication($c->get(Connection::class)));

		$wiring->route(array('extern.php'), ExternController::class, fn (Container $c): object => new ExternController(
			$c->get(EventDispatcher::class),
			$c->get(TemplateRenderer::class),
			$c->get(SyndicationInterface::class),
			$c->get(BasicAuthenticationInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class)
		), quiet: true);
	}
}
