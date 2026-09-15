<?php

declare(strict_types=1);

namespace PunBB\Module\Database;

use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;

/**
 * Prepared statements over the forum's database connection, for the
 * repositories behind the modules' service contracts.
 *
 * The connection itself is opened by the bootstrap, so this module wires
 * nothing: the bootstrap's side wires Sql\Connection over its handle.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Database';
	}

	public function dependencies(): array {
		return array('Framework');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {}
}
