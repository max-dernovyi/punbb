<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Patch;

use PunBB\Module\Framework\Container\Container;
use Throwable;

/**
 * Applies the declared data patches a board has not recorded, and records
 * each once its last batch is applied. There is no rollback: a patch changes
 * data, and once recorded it is never undone. A patch that fails is not
 * recorded, so the next run starts again at it.
 */
final class PatchApplier {
	public function __construct(
		private readonly DeclaredPatches $declared,
		private readonly AppliedPatchesInterface $applied,
		private readonly Container $container
	) {}

	/** @return list<PatchDeclaration> the declared patches the board has not recorded, in the order they apply */
	public function pending(): array {
		$applied = array_flip($this->applied->names());

		return array_values(array_filter($this->declared->ordered(), static fn (PatchDeclaration $patch): bool => !isset($applied[$patch->name])));
	}

	/**
	 * The batch of $patch from $startAt on, recorded as applied once it is the last.
	 *
	 * @throws PatchException the patch failed; it is not recorded
	 */
	public function apply(PatchDeclaration $patch, int $startAt = 0): PatchStep {
		try {
			$step = ($patch->factory)($this->container)->apply($startAt);
		}
		catch (Throwable $e) {
			throw new PatchException(sprintf('Data patch %s failed', $patch->name), 0, $e);
		}

		if ($step->next === null)
			$this->applied->record($patch->name);

		return $step;
	}

	/** Records every patch but those of the modules $except without applying it: a fresh install writes its data in the shape the patches lead to. */
	public function recordAll(string ...$except): void {
		$except = array_flip($except);

		foreach ($this->pending() as $patch)
			if (!isset($except[$patch->module()]))
				$this->applied->record($patch->name);
	}
}
