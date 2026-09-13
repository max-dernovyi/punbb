<?php

declare(strict_types=1);

namespace PunBB\Module\Framework;

use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;

/**
 * The container and the module registry every other module is built on.
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

	public function wire(Wiring $wiring): void {}
}
