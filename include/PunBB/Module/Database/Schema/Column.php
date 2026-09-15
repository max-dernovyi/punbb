<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema;

/**
 * A column of a table, in the types the schema declares: 'VARCHAR(80)',
 * 'INT(10) UNSIGNED', 'SERIAL'. Each driver translates them into its own.
 */
final readonly class Column {
	/**
	 * @param int|string|null $default what a row gets when it names no value: a number, or text; null for no default
	 * @param ?string $collation the collation of a text column, by its suffix: 'bin'
	 */
	public function __construct(
		public string $name,
		public string $type,
		public bool $nullable = false,
		public int|string|null $default = null,
		public ?string $collation = null
	) {}
}
