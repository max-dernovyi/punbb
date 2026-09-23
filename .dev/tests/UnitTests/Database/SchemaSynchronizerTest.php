<?php
/**
 * The synchronizer reads each declared table through the schema, and makes
 * what the differ asks for through it, in declared order.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Schema\Change\ChangeInterface;
use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\DeclaredSchema;
use PunBB\Module\Database\Schema\InstalledColumn;
use PunBB\Module\Database\Schema\InstalledTable;
use PunBB\Module\Database\Schema\SchemaInterface;
use PunBB\Module\Database\Schema\SchemaSynchronizer;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;

class SchemaSynchronizerTest extends TestCase {
	private static function schema(): DeclaredSchema {
		return new DeclaredSchema(new class() implements ModuleInterface, TableOwnerInterface {
			public function name(): string { return 'Topic'; }

			public function dependencies(): array { return array(); }

			public function loadAfter(): array { return array(); }

			public function wire(Wiring $wiring): void {}

			public function tables(Platform $platform): array {
				return array(
					new Table('topics', array(new Column('id', 'SERIAL'), new Column('subject', 'VARCHAR(255)', false, '')), array('id')),
					new Table('posts', array(new Column('id', 'SERIAL'), new Column('message', 'TEXT', true)), array('id'), array(), array('message_idx' => array('message'))),
				);
			}
		});
	}

	/** @param array<string, InstalledTable> $installed */
	private static function database(array $installed): SchemaInterface {
		return new class($installed) implements SchemaInterface {
			/** @var list<string> */
			public array $made = array();

			/** @param array<string, InstalledTable> $installed */
			public function __construct(private array $installed) {}

			public function describe(string $table): ?InstalledTable { return $this->installed[$table] ?? null; }

			public function tableExists(string $table): bool { return isset($this->installed[$table]); }

			public function fieldExists(string $table, string $field): bool { return false; }

			public function indexExists(string $table, string $index): bool { return false; }

			public function createTable(Table $table): void { $this->made[] = 'create '.$table->name; }

			public function addField(string $table, Column $column, ?string $after = null): void { $this->made[] = 'add '.$table.'.'.$column->name.' after '.$after; }

			public function alterField(string $table, Column $column, ?string $after = null): void { $this->made[] = 'alter '.$table.'.'.$column->name; }

			public function dropField(string $table, string $field): void { $this->made[] = 'drop '.$table.'.'.$field; }

			public function addIndex(string $table, string $index, array $columns, bool $unique = false): void { $this->made[] = 'index '.$table.'.'.$index; }

			public function dropIndex(string $table, string $index): void { $this->made[] = 'drop index '.$table.'.'.$index; }
		};
	}

	public function testWhatTheDatabaseLacksIsListedTableByTable(): void {
		$database = self::database(array('posts' => new InstalledTable('posts', array(new InstalledColumn('id', 'int unsigned', false, null)), array('id'))));
		$changes = (new SchemaSynchronizer(self::schema(), $database))->changes(Platform::Mysql);

		$this->assertSame(array('create table topics', 'add column posts.message TEXT', 'add index posts.message_idx (message)'), array_map(static fn (ChangeInterface $change): string => $change->describe(), $changes));
		$this->assertSame(array(), $database->made, 'listing the changes makes none');
	}

	public function testSynchronizingMakesEachChangeThroughTheSchema(): void {
		$database = self::database(array('posts' => new InstalledTable('posts', array(new InstalledColumn('id', 'int unsigned', false, null)), array('id'))));
		$made = (new SchemaSynchronizer(self::schema(), $database))->synchronize(Platform::Mysql);

		$this->assertCount(3, $made);
		$this->assertSame(array('create topics', 'add posts.message after id', 'index posts.message_idx'), $database->made);
	}

	public function testAnUpToDateDatabaseIsLeftAlone(): void {
		$database = self::database(array(
			'topics'	=> new InstalledTable('topics', array(new InstalledColumn('id', 'integer', false, null), new InstalledColumn('subject', 'character varying(255)', false, '')), array('id')),
			'posts'		=> new InstalledTable('posts', array(new InstalledColumn('id', 'integer', false, null), new InstalledColumn('message', 'text', true, null)), array('id'), array(new PunBB\Module\Database\Schema\InstalledIndex('message_idx', array('message'), false))),
		));

		$this->assertSame(array(), (new SchemaSynchronizer(self::schema(), $database))->synchronize(Platform::Pgsql));
		$this->assertSame(array(), $database->made);
	}
}
