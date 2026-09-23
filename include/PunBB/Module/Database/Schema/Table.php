<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema;

/**
 * A table as the schema declares it. A key or an index column may carry the
 * length of the prefix MySQL indexes: 'ident(8)'. What an earlier release of
 * the table had and this one has not is named, so it is dropped where a board
 * still has it; a column or index nobody named is left alone.
 */
final readonly class Table {
	/**
	 * @param string $name unprefixed
	 * @param list<Column> $columns
	 * @param list<string> $primaryKey
	 * @param array<string, list<string>> $uniqueKeys name => its columns
	 * @param array<string, list<string>> $indexes name => its columns
	 * @param ?string $engine the MySQL storage engine, null for the driver's own
	 * @param list<string> $removedColumns columns an earlier release of the table had
	 * @param list<string> $removedIndexes indexes an earlier release of the table had
	 */
	public function __construct(
		public string $name,
		public array $columns,
		public array $primaryKey = array(),
		public array $uniqueKeys = array(),
		public array $indexes = array(),
		public ?string $engine = null,
		public array $removedColumns = array(),
		public array $removedIndexes = array()
	) {}
}
