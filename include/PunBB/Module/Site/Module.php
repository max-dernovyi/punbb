<?php

declare(strict_types=1);

namespace PunBB\Module\Site;

use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;

/**
 * What a page reads about the site it is served on: the visitor, the board's
 * settings, the language pack, the URL scheme and the formats text is shown
 * in; and the board-wide work pages share, such as the caches they rebuild.
 * The module declares them; the bootstrap's side wires them.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Site';
	}

	public function dependencies(): array {
		return array('Framework', 'Layout');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {}
}
