<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Version;

/**
 * What a board records for one module: the version its tables are at and the
 * version its data is at, apart. The schema is brought up before the data, so
 * a current schema with data patches outstanding is a state a board is in.
 */
final readonly class InstalledVersion {
	/** A module the board has never recorded: before every release. */
	public const ZERO = '0';

	public function __construct(
		public string $schema = self::ZERO,
		public string $data = self::ZERO
	) {}
}
