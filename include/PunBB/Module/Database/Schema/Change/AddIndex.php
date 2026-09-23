<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema\Change;

use PunBB\Module\Database\Schema\SchemaInterface;

/** An index or unique key the table lacks. */
final readonly class AddIndex implements ChangeInterface {
	/** @param list<string> $columns */
	public function __construct(
		public string $table,
		public string $index,
		public array $columns,
		public bool $unique
	) {}

	public function applyTo(SchemaInterface $schema): void {
		$schema->addIndex($this->table, $this->index, $this->columns, $this->unique);
	}

	public function describe(): string {
		return 'add '.($this->unique ? 'unique key ' : 'index ').$this->table.'.'.$this->index.' ('.implode(', ', $this->columns).')';
	}
}
