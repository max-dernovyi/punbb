<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Version;

use PunBB\Module\Database\Patch\PatchApplier;
use PunBB\Module\Database\Patch\PatchDeclaration;
use PunBB\Module\Database\Patch\PatchException;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Database\Schema\Change\ChangeInterface;
use PunBB\Module\Database\Schema\SchemaSynchronizer;
use PunBB\Module\Database\Sql\Platform;

/**
 * Brings each module behind its declared version up to it, module by module
 * in load order: the tables of a module whose schema is behind, then the
 * data patches of a module whose data is behind. A module's version is
 * recorded once its part is done, so a board that records none — every board
 * from before module versions — has every module brought up once.
 */
final class ModuleUpgrade {
	public function __construct(
		private readonly ModuleVersions $versions,
		private readonly SchemaSynchronizer $synchronizer,
		private readonly PatchApplier $patches
	) {}

	/** @return list<string> the modules whose schema or data is behind, in load order */
	public function behind(): array {
		return $this->versions->behind();
	}

	/**
	 * Synchronizes the tables of each module whose schema is behind and no
	 * other, then records their schema versions: the table they are recorded
	 * in may be among those synchronized.
	 *
	 * @return list<ChangeInterface> the changes made
	 */
	public function schema(Platform $platform): array {
		$behind = $this->versions->behindOnSchema();

		$changes = array();
		foreach ($behind as $module)
			array_push($changes, ...$this->synchronizer->synchronizeModule($module, $platform));

		foreach ($behind as $module)
			$this->versions->recordSchema($module);

		return $changes;
	}

	/** @return list<PatchDeclaration> the patches the board has not recorded of each module whose data is behind, in the order they apply */
	public function pending(): array {
		$behind = array_flip($this->versions->behindOnData());

		return array_values(array_filter($this->patches->pending(), static fn (PatchDeclaration $patch): bool => isset($behind[$patch->module()])));
	}

	/**
	 * The batch of $patch from $startAt on; once it completes and its module
	 * has no patch left, the module's data is recorded at its version.
	 *
	 * @throws PatchException the patch failed; neither it nor its module is recorded
	 */
	public function apply(PatchDeclaration $patch, int $startAt = 0): PatchStep {
		$step = $this->patches->apply($patch, $startAt);

		if ($step->next === null && !in_array($patch->module(), $this->pendingModules(), true) && $this->versions->dataBehind($patch->module()))
			$this->versions->recordData($patch->module());

		return $step;
	}

	/** Records the data of every module behind on it with no patch left: every module once the patches are done. */
	public function recordData(): void {
		$pending = $this->pendingModules();

		foreach ($this->versions->behindOnData() as $module)
			if (!in_array($module, $pending, true))
				$this->versions->recordData($module);
	}

	/** @return list<string> */
	private function pendingModules(): array {
		return array_values(array_unique(array_map(static fn (PatchDeclaration $patch): string => $patch->module(), $this->pending())));
	}
}
