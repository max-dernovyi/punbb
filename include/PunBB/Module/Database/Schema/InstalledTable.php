<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema;

/**
 * A table as the database reports it.
 */
final readonly class InstalledTable {
	/** @var array<string, InstalledColumn> name => column, in the table's order */
	public array $columns;

	/**
	 * @param string $name unprefixed
	 * @param list<InstalledColumn> $columns
	 * @param list<string> $primaryKey
	 * @param list<InstalledIndex> $indexes
	 */
	public function __construct(
		public string $name,
		array $columns,
		public array $primaryKey = array(),
		public array $indexes = array()
	) {
		$named = array();
		foreach ($columns as $column)
			$named[$column->name] = $column;

		$this->columns = $named;
	}

	public function column(string $name): ?InstalledColumn {
		return $this->columns[$name] ?? null;
	}

	public function index(string $name): ?InstalledIndex {
		foreach ($this->indexes as $index)
			if ($index->name === $name)
				return $index;

		return null;
	}
}
