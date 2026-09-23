<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Version;

/**
 * The module versions a board records.
 */
interface InstalledVersionsInterface {
	/** @return array<string, InstalledVersion> module => its versions, for every module the board records; none on a board without the table */
	public function all(): array;

	/** Records $module's tables at $version; its data version stays what it was. */
	public function recordSchema(string $module, string $version): void;

	/** Records $module's data at $version; its schema version stays what it was. */
	public function recordData(string $module, string $version): void;
}
