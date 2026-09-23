<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema;

/**
 * A column as the database reports it: its type in the database's own words,
 * its default as a plain value.
 */
final readonly class InstalledColumn {
	/**
	 * @param string $type lowercase: 'int unsigned', 'character varying(80)', 'integer'
	 * @param ?string $default null for none, and for the sequence behind a SERIAL column
	 * @param ?string $collation where the database reports one: 'utf8mb3_bin'
	 */
	public function __construct(
		public string $name,
		public string $type,
		public bool $nullable,
		public ?string $default,
		public ?string $collation = null
	) {}
}
