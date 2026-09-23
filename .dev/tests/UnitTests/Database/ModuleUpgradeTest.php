<?php
/**
 * The upgrade, constructed directly over three modules: it brings up the
 * tables of a module whose schema is behind and no other's, applies the
 * patches of a module whose data is behind and no other's, and records a
 * module's data only once its last patch completes. A board recording no
 * module has every one brought up once, and a second run does nothing.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\DeclaredPatches;
use PunBB\Module\Database\Patch\PatchApplier;
use PunBB\Module\Database\Patch\PatchDeclaration;
use PunBB\Module\Database\Patch\PatchException;
use PunBB\Module\Database\Patch\PatchOwnerInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\DeclaredSchema;
use PunBB\Module\Database\Schema\SchemaSynchronizer;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Database\Version\InstalledVersion;
use PunBB\Module\Database\Version\ModuleUpgrade;
use PunBB\Module\Database\Version\ModuleVersions;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;

require_once FORUM_ROOT.'.dev/tests/UnitTests/Page/SetupFakes.php';

/** Rows 1 to $rows, one a batch, journaled; row $failAt throws. */
final class JournaledRowsPatch implements DataPatchInterface {
	public function __construct(private readonly SetupJournal $journal, private readonly string $name, private readonly int $rows, private readonly ?int $failAt = null) {}

	public function apply(int $startAt): PatchStep {
		$row = max(1, $startAt);
		if ($row === $this->failAt)
			throw new RuntimeException('row '.$row.' is unreadable');

		$this->journal->add('patch '.$this->name.' row '.$row);

		return new PatchStep(array(), $row < $this->rows ? $row + 1 : null);
	}
}

class ModuleUpgradeTest extends TestCase {
	private SetupJournal $journal;

	private FakeSchema $schema;

	private JournalAppliedPatches $applied;

	private JournalInstalledVersions $installed;

	/** The row Forums::names fails at. */
	private ?int $failAt = null;

	protected function setUp(): void {
		$this->journal = new SetupJournal();
		$this->schema = new FakeSchema($this->journal);
		$this->applied = new JournalAppliedPatches($this->journal);
		$this->installed = new JournalInstalledVersions($this->journal);
	}

	/**
	 * @param list<string> $tables
	 * @param array<string, int> $patches patch name => its rows
	 */
	private function module(string $name, string $version, array $tables, array $patches): ModuleInterface {
		$patch = fn (string $patch, int $rows): PatchDeclaration => new PatchDeclaration($patch, array(), fn (Container $c): DataPatchInterface => new JournaledRowsPatch($this->journal, $patch, $rows, $patch === 'Forums::names' ? $this->failAt : null));

		return new class($name, $version, $tables, array_map($patch, array_keys($patches), $patches)) implements ModuleInterface, TableOwnerInterface, PatchOwnerInterface {
			/**
			 * @param list<string> $tables
			 * @param list<PatchDeclaration> $patches
			 */
			public function __construct(private string $name, private string $version, private array $tables, private array $patches) {}

			public function name(): string { return $this->name; }

			public function dependencies(): array { return array(); }

			public function loadAfter(): array { return array(); }

			public function version(): string { return $this->version; }

			public function wire(Wiring $wiring): void {}

			public function tables(Platform $platform): array {
				return array_map(static fn (string $table): Table => new Table($table, array(new Column('id', 'SERIAL')), array('id')), $this->tables);
			}

			public function patches(): array { return $this->patches; }
		};
	}

	private function upgrade(): ModuleUpgrade {
		$modules = array(
			$this->module('Forums', '1.4.0', array('forums'), array('Forums::counts' => 1, 'Forums::names' => 3)),
			$this->module('Help', '2.0.0', array(), array()),
			$this->module('Polls', '1.1.0', array('polls', 'votes'), array('Polls::votes' => 1)),
		);

		return new ModuleUpgrade(
			new ModuleVersions($this->installed, ...$modules),
			new SchemaSynchronizer(new DeclaredSchema(...$modules), $this->schema),
			new PatchApplier(new DeclaredPatches(...$modules), $this->applied, new Container(array()))
		);
	}

	/** Applies every pending patch a batch at a time; @return int the batches */
	private function applyAll(ModuleUpgrade $upgrade): int {
		$batches = 0;
		while (($pending = $upgrade->pending()) !== array() && $batches < 20)
		{
			$next = 0;
			do
			{
				$next = $upgrade->apply($pending[0], $next)->next;
				++$batches;
			}
			while ($next !== null);
		}

		return $batches;
	}

	public function testABoardRecordingNoModuleHasEveryModuleBroughtUpOnce(): void {
		$upgrade = $this->upgrade();

		$this->assertSame(array('Forums', 'Help', 'Polls'), $upgrade->behind());
		$this->assertCount(3, $upgrade->schema(Platform::Mysql));
		$this->assertSame(array('create forums', 'create polls', 'create votes', 'version Forums schema 1.4.0', 'version Help schema 2.0.0', 'version Polls schema 1.1.0'), $this->journal->entries, 'the tables first, then the versions: the table recording them may be among the tables');
		$this->assertSame(array('Forums::counts', 'Forums::names', 'Polls::votes'), array_map(static fn (PatchDeclaration $patch): string => $patch->name, $upgrade->pending()), 'a current schema with the data behind');

		$this->journal->entries = array();
		$this->assertSame(5, $this->applyAll($upgrade));
		$upgrade->recordData();

		$this->assertSame(array(
			'patch Forums::counts row 1', 'record Forums::counts',
			'patch Forums::names row 1', 'patch Forums::names row 2', 'patch Forums::names row 3', 'record Forums::names', 'version Forums data 1.4.0',
			'patch Polls::votes row 1', 'record Polls::votes', 'version Polls data 1.1.0',
			'version Help data 2.0.0',
		), $this->journal->entries, 'a module\'s data once its last patch completes; one with no patch once every patch has run');
		$this->assertSame(array(), $upgrade->behind());

		// Run again, it finds nothing to do
		$this->journal->entries = array();
		$this->assertSame(array(), $upgrade->schema(Platform::Mysql));
		$this->assertSame(array(), $upgrade->pending());
		$upgrade->recordData();
		$this->assertSame(array(), $this->journal->entries);
	}

	public function testOnlyTheModuleBehindIsBroughtUp(): void {
		$this->installed->versions = array('Forums' => new InstalledVersion('1.4.0', '1.4.0'), 'Help' => new InstalledVersion('2.0.0', '2.0.0'), 'Polls' => new InstalledVersion('1.0.0', '1.0.0'));
		$upgrade = $this->upgrade();

		// None of the tables exists, and none of the patches is recorded
		$this->assertCount(2, $upgrade->schema(Platform::Pgsql));
		$this->assertSame(array('create polls', 'create votes', 'version Polls schema 1.1.0'), $this->journal->entries);
		$this->assertSame(array('Polls::votes'), array_map(static fn (PatchDeclaration $patch): string => $patch->name, $upgrade->pending()), 'the patches of a module whose data is current are not looked for');
	}

	public function testAPatchThatFailsLeavesItsModulesDataUnrecorded(): void {
		$this->installed->versions = array('Help' => new InstalledVersion('2.0.0', '2.0.0'), 'Polls' => new InstalledVersion('1.1.0', '1.1.0'));
		$this->failAt = 2;
		$upgrade = $this->upgrade();

		$upgrade->apply($upgrade->pending()[0]);
		$names = $upgrade->pending()[0];
		$next = $upgrade->apply($names)->next;

		try {
			$upgrade->apply($names, (int) $next);
			$this->fail('the unreadable row stops the patch');
		}
		catch (PatchException $e) {
			$this->assertSame('Data patch Forums::names failed', $e->getMessage());
		}

		$upgrade->recordData();

		$this->assertSame(array(), $this->journal->starting('version'), 'Forums has a patch left, so its data is not recorded');
		$this->assertSame(array('Forums'), $upgrade->behind());
	}
}
