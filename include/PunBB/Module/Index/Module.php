<?php

declare(strict_types=1);

namespace PunBB\Module\Index;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Index\Api\BoardIndexInterface;
use PunBB\Module\Index\Controller\IndexController;
use PunBB\Module\Index\Interceptor\BoardIndexInterceptor;
use PunBB\Module\Index\Model\BoardIndex;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The board index: the forums, the board's statistics and who is online.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Index';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(BoardIndexInterface::class, BoardIndexInterceptor::class, fn (Container $c): object => new BoardIndex($c->get(Connection::class)));

		$wiring->route(array('index.php', ''), IndexController::class, fn (Container $c): object => new IndexController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(BoardIndexInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class)
		));
	}
}
