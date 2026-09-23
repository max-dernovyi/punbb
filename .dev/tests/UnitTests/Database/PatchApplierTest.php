<?php
/**
 * The applier, constructed directly over declared patches and a record of the
 * applied ones: a patch runs until its last batch, is recorded then and never
 * again, and one that fails is left pending.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Database\Patch\AppliedPatchesInterface;
use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\DeclaredPatches;
use PunBB\Module\Database\Patch\PatchApplier;
use PunBB\Module\Database\Patch\PatchDeclaration;
use PunBB\Module\Database\Patch\PatchException;
use PunBB\Module\Database\Patch\PatchOwnerInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;

final class MemoryAppliedPatches implements AppliedPatchesInterface {
	/** @var list<string> */
	public array $names = array();

	public function names(): array { return $this->names; }

	public function record(string $name): void { $this->names[] = $name; }
}

/** Rows 1 to $rows, two a batch; each batch logs the rows it handled. */
final class BatchedPatch implements DataPatchInterface {
	/** @param ArrayObject<int, string> $log */
	public function __construct(private readonly ArrayObject $log, private readonly int $rows, private readonly ?int $failAt = null) {}

	public function apply(int $startAt): PatchStep {
		$from = max(1, $startAt);
		if ($from === $this->failAt)
			throw new RuntimeException('row '.$from.' is unreadable');

		$lines = array();
		for ($row = $from; $row < $from + 2 && $row <= $this->rows; ++$row)
			$lines[] = 'row '.$row;

		$this->log->append(implode(',', $lines));

		return new PatchStep($lines, $from + 2 <= $this->rows ? $from + 2 : null);
	}
}

class PatchApplierTest extends TestCase {
	private MemoryAppliedPatches $applied;

	/** @var ArrayObject<int, string> */
	private ArrayObject $log;

	protected function setUp(): void {
		$this->applied = new MemoryAppliedPatches();
		$this->log = new ArrayObject();
	}

	private function applier(?int $failAt = null): PatchApplier {
		$log = $this->log;
		$module = new class($log, $failAt) implements ModuleInterface, PatchOwnerInterface {
			/** @param ArrayObject<int, string> $log */
			public function __construct(private ArrayObject $log, private ?int $failAt) {}

			public function name(): string { return 'Forums'; }

			public function dependencies(): array { return array(); }

			public function loadAfter(): array { return array(); }

			public function version(): string { return '1.0.0'; }

			public function wire(Wiring $wiring): void {}

			public function patches(): array {
				return array(
					new PatchDeclaration('Forums::counts', array(), fn (Container $c): DataPatchInterface => new BatchedPatch($this->log, 1)),
					new PatchDeclaration('Forums::names', array('Forums::counts'), fn (Container $c): DataPatchInterface => new BatchedPatch($this->log, 5, $this->failAt)),
				);
			}
		};

		return new PatchApplier(new DeclaredPatches($module), $this->applied, new Container(array()));
	}

	/** @return list<string> */
	private static function names(PatchApplier $applier): array {
		return array_map(static fn (PatchDeclaration $patch): string => $patch->name, $applier->pending());
	}

	public function testEveryDeclaredPatchIsPendingOnABoardThatRecordedNone(): void {
		$this->assertSame(array('Forums::counts', 'Forums::names'), self::names($this->applier()));
	}

	public function testAPatchIsRecordedWithItsLastBatchAndNotBefore(): void {
		$applier = $this->applier();
		$names = $applier->pending()[1];

		$this->assertSame(3, $applier->apply($names)->next);
		$this->assertSame(array(), $this->applied->names);

		$this->assertSame(5, $applier->apply($names, 3)->next);
		$step = $applier->apply($names, 5);

		$this->assertSame(array('row 5'), $step->lines);
		$this->assertNull($step->next);
		$this->assertSame(array('Forums::names'), $this->applied->names);
		$this->assertSame(array('Forums::counts'), self::names($applier));
	}

	public function testAnAppliedPatchIsNotAppliedAgain(): void {
		$applier = $this->applier();
		while (($pending = $applier->pending()) !== array())
		{
			$startAt = 0;
			do
				$startAt = $applier->apply($pending[0], $startAt)->next;
			while ($startAt !== null);
		}

		$this->assertSame(array('Forums::counts', 'Forums::names'), $this->applied->names);
		$this->assertSame(array('row 1', 'row 1,row 2', 'row 3,row 4', 'row 5'), $this->log->getArrayCopy());

		$this->assertSame(array(), $this->applier()->pending(), 'a second run finds nothing to apply');
	}

	public function testAFailedPatchIsNamedAndLeftPending(): void {
		$applier = $this->applier(3);
		$applier->apply($applier->pending()[0]);
		$names = $applier->pending()[0];
		$applier->apply($names);

		try {
			$applier->apply($names, 3);
			$this->fail('the patch did not fail');
		}
		catch (PatchException $e) {
			$this->assertSame('Data patch Forums::names failed', $e->getMessage());
			$this->assertSame('row 3 is unreadable', $e->getPrevious()?->getMessage());
		}

		$this->assertSame(array('Forums::counts'), $this->applied->names);
		$this->assertSame(array('Forums::names'), self::names($applier));
	}

	public function testAFreshInstallRecordsEveryPatchWithoutApplyingOne(): void {
		$this->applied->names = array('Forums::counts');
		$this->applier()->recordAll();

		$this->assertSame(array('Forums::counts', 'Forums::names'), $this->applied->names);
		$this->assertSame(array(), $this->log->getArrayCopy());
	}
}
