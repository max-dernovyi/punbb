<?php

declare(strict_types=1);

namespace PunBBModule\Autograph;

use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Index\Event\IndexRendering;
use PunBBModule\Autograph\Observer\SignatureObserver;
use PunBBModule\Guestbook\Model\Entries;

/**
 * Fixture, unpacked into modules/ beside the Guestbook it depends on, which
 * its name sorts before: owns the autographs table and signs below the
 * guestbook's entries.
 */
final class Module implements ModuleInterface, TableOwnerInterface {
	public function name(): string {
		return 'Autograph';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Index', 'Guestbook');
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '1.0.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->observer(IndexRendering::class, SignatureObserver::class, fn (Container $c): object => new SignatureObserver($c->get(Entries::class)));
	}

	public function tables(Platform $platform): array {
		return array(new Table('autographs', array(
			new Column('id', 'SERIAL'),
			new Column('signature', 'VARCHAR(200)', false, ''),
		), array('id')));
	}
}
