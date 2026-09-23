<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Patch;

/**
 * The data patches a board records as applied.
 */
interface AppliedPatchesInterface {
	/** @return list<string> */
	public function names(): array;

	public function record(string $name): void;
}
