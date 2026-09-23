<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema\Change;

use PunBB\Module\Database\Schema\SchemaInterface;

/** A column an earlier release of the table had. */
final readonly class DropColumn implements ChangeInterface {
	public function __construct(
		public string $table,
		public string $column
	) {}

	public function applyTo(SchemaInterface $schema): void {
		$schema->dropField($this->table, $this->column);
	}

	public function describe(): string {
		return 'drop column '.$this->table.'.'.$this->column;
	}
}
