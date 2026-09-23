<?php

declare(strict_types=1);

namespace PunBB\Module\AdminIndex;

use PunBB\Module\AdminIndex\Api\BoardInformationInterface;
use PunBB\Module\AdminIndex\Controller\InformationController;
use PunBB\Module\AdminIndex\Interceptor\BoardInformationInterceptor;
use PunBB\Module\AdminIndex\Model\BoardInformation;
use PunBB\Module\AdminIndex\Model\ServerEnvironment;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The administration's index: what the board and its server report.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'AdminIndex';
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
		$wiring->contract(BoardInformationInterface::class, BoardInformationInterceptor::class, fn (Container $c): object => new BoardInformation($c->get(Connection::class)));
		$wiring->service(ServerEnvironment::class, fn (): object => new ServerEnvironment());

		$wiring->route(array('admin/index.php', 'admin/', 'admin'), InformationController::class, fn (Container $c): object => new InformationController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(BoardInformationInterface::class),
			$c->get(ServerEnvironment::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class)
		));
	}
}
