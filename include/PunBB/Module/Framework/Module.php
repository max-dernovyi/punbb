<?php

declare(strict_types=1);

namespace PunBB\Module\Framework;

use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Framework\Routing\FrontController;

/**
 * The container, the module registry and the routing every other module is built on.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Framework';
	}

	public function dependencies(): array {
		return array();
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '2.0.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->service(FrontController::class, fn (Container $c): object => new FrontController($c));
	}
}
