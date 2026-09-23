<?php

declare(strict_types=1);

namespace PunBB\Module\Database;

use PunBB\Module\Database\Patch\AppliedPatches;
use PunBB\Module\Database\Patch\AppliedPatchesInterface;
use PunBB\Module\Database\Patch\DeclaredPatches;
use PunBB\Module\Database\Patch\PatchApplier;
use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\DeclaredSchema;
use PunBB\Module\Database\Schema\SchemaInterface;
use PunBB\Module\Database\Schema\SchemaSynchronizer;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\Wiring;

/**
 * Prepared statements over the forum's database connection, for the
 * repositories behind the modules' service contracts; the schema the modules
 * declare, synchronized through the schema driver; and the data patches they
 * declare, applied once and recorded in data_patches.
 *
 * The connection itself is opened by the bootstrap: the bootstrap's side
 * wires Sql\Connection over its handle, and the SchemaInterface over its driver.
 */
final class Module implements ModuleInterface, TableOwnerInterface {
	public function name(): string {
		return 'Database';
	}

	public function dependencies(): array {
		return array('Framework');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->service(DeclaredSchema::class, fn (Container $c): object => new DeclaredSchema(...$c->get(ModuleRegistry::class)->modules()));
		$wiring->service(SchemaSynchronizer::class, fn (Container $c): object => new SchemaSynchronizer($c->get(DeclaredSchema::class), $c->get(SchemaInterface::class)));
		$wiring->service(DeclaredPatches::class, fn (Container $c): object => new DeclaredPatches(...$c->get(ModuleRegistry::class)->modules()));
		$wiring->service(AppliedPatchesInterface::class, fn (Container $c): object => new AppliedPatches($c->get(Connection::class)));
		$wiring->service(PatchApplier::class, fn (Container $c): object => new PatchApplier($c->get(DeclaredPatches::class), $c->get(AppliedPatchesInterface::class), $c));
	}

	public function tables(Platform $platform): array {
		return array(
			new Table('data_patches', array(
				new Column('name', 'VARCHAR(150)', false, ''),
				new Column('applied', 'INT(10) UNSIGNED', false, 0),
			), array('name')),
		);
	}
}
