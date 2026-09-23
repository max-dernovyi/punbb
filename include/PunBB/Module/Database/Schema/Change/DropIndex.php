<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema\Change;

use PunBB\Module\Database\Schema\SchemaInterface;

/** An index an earlier release of the table had, or one over other columns than declared, dropped to be added again. */
final readonly class DropIndex implements ChangeInterface {
	public function __construct(
		public string $table,
		public string $index
	) {}

	public function applyTo(SchemaInterface $schema): void {
		$schema->dropIndex($this->table, $this->index);
	}

	public function describe(): string {
		return 'drop index '.$this->table.'.'.$this->index;
	}
}
