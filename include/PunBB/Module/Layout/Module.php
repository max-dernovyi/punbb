<?php

declare(strict_types=1);

namespace PunBB\Module\Layout;

use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Chrome\ChromeFactoryInterface;
use PunBB\Module\Layout\Chrome\Layout;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;

/**
 * The page chrome: its templates, the regions that fill them and the events
 * extension code runs at while they are built.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Layout';
	}

	public function dependencies(): array {
		return array('Framework');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->service(TemplateRenderer::class, fn (): object => new TemplateRenderer());
		$wiring->service(Layout::class, fn (Container $c): object => new Layout($c->get(EventDispatcher::class), $c->get(TemplateRenderer::class)));
		$wiring->service(PageResponder::class, fn (Container $c): object => new PageResponder($c->get(ChromeFactoryInterface::class)));
	}
}
