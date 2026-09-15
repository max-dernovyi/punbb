<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema;

/**
 * A table as the schema declares it. A key or an index column may carry the
 * length of the prefix MySQL indexes: 'ident(8)'.
 */
final readonly class Table {
	/**
	 * @param string $name unprefixed
	 * @param list<Column> $columns
	 * @param list<string> $primaryKey
	 * @param array<string, list<string>> $uniqueKeys name => its columns
	 * @param array<string, list<string>> $indexes name => its columns
	 * @param ?string $engine the MySQL storage engine, null for the driver's own
	 */
	public function __construct(
		public string $name,
		public array $columns,
		public array $primaryKey = array(),
		public array $uniqueKeys = array(),
		public array $indexes = array(),
		public ?string $engine = null
	) {}
}
