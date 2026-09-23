<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Patch;

/**
 * A change to a board's data that the declared schema cannot express, applied
 * once. It runs after the schema is synchronized, so it finds every declared
 * column; it decides for itself whether the board needs it, and finding
 * nothing to do completes it. What it does must survive being done twice: a
 * failed patch is applied again from the start.
 */
interface DataPatchInterface {
	/** The batch from $startAt on: 0 for the first, then the step's next. */
	public function apply(int $startAt): PatchStep;
}
