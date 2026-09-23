<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema\Change;

use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\SchemaInterface;

/** A column the table lacks, placed after the column declared before it. */
final readonly class AddColumn implements ChangeInterface {
	public function __construct(
		public string $table,
		public Column $column,
		public ?string $after
	) {}

	public function applyTo(SchemaInterface $schema): void {
		$schema->addField($this->table, $this->column, $this->after);
	}

	public function describe(): string {
		return 'add column '.$this->table.'.'.$this->column->name.' '.$this->column->type;
	}
}
