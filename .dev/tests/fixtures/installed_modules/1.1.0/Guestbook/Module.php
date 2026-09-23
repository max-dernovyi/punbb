<?php

declare(strict_types=1);

namespace PunBBModule\Guestbook;

use PunBB\Module\Database\Patch\PatchDeclaration;
use PunBB\Module\Database\Patch\PatchOwnerInterface;
use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Index\Api\BoardIndexInterface;
use PunBB\Module\Index\Event\IndexRendering;
use PunBBModule\Guestbook\Model\Entries;
use PunBBModule\Guestbook\Observer\EntriesObserver;
use PunBBModule\Guestbook\Patch\ApproveEntries;
use PunBBModule\Guestbook\Plugin\StatisticsPlugin;

/**
 * Fixture, the Guestbook's next release unpacked over 1.0.0: the table gains
 * the approved column, and a data patch approves the entries taken before it.
 */
final class Module implements ModuleInterface, TableOwnerInterface, PatchOwnerInterface {
	public function name(): string {
		return 'Guestbook';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Index');
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '1.1.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->service(Entries::class, fn (Container $c): object => new Entries($c->get(Connection::class)));
		$wiring->plugin(BoardIndexInterface::class, StatisticsPlugin::class, fn (Container $c): object => new StatisticsPlugin($c->get(Entries::class)));
		$wiring->observer(IndexRendering::class, EntriesObserver::class, fn (Container $c): object => new EntriesObserver($c->get(Entries::class)));
	}

	public function tables(Platform $platform): array {
		return array(new Table('guestbook', array(
			new Column('id', 'SERIAL'),
			new Column('poster', 'VARCHAR(200)', false, ''),
			new Column('approved', 'TINYINT(1)', false, 0),
		), array('id')));
	}

	public function patches(): array {
		return array(
			new PatchDeclaration('Guestbook::approve_entries', array(), static fn (Container $c): ApproveEntries => new ApproveEntries($c->get(Connection::class))),
		);
	}
}
