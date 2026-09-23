<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema\Change;

use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\SchemaInterface;

/** A column of another type, nullability, default or collation than declared. */
final readonly class AlterColumn implements ChangeInterface {
	public function __construct(
		public string $table,
		public Column $column
	) {}

	public function applyTo(SchemaInterface $schema): void {
		$schema->alterField($this->table, $this->column);
	}

	public function describe(): string {
		return 'alter column '.$this->table.'.'.$this->column->name.' to '.$this->column->type.($this->column->nullable ? ' NULL' : ' NOT NULL').($this->column->default !== null ? ' DEFAULT \''.$this->column->default.'\'' : '');
	}
}
