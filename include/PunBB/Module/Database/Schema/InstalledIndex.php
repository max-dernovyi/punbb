<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema;

/**
 * An index or unique key as the database reports it, the primary key aside.
 */
final readonly class InstalledIndex {
	/**
	 * @param ?string $name as a table declares it, without the table's prefixed name; null for a name the database made up
	 * @param list<string> $columns in order, each with the prefix length MySQL indexes: 'ident(40)'
	 */
	public function __construct(
		public ?string $name,
		public array $columns,
		public bool $unique
	) {}
}
