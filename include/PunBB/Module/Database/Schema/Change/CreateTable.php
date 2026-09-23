<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema\Change;

use PunBB\Module\Database\Schema\SchemaInterface;
use PunBB\Module\Database\Schema\Table;

/** A table the database lacks, created with its keys and indexes. */
final readonly class CreateTable implements ChangeInterface {
	public function __construct(public Table $table) {}

	public function applyTo(SchemaInterface $schema): void {
		$schema->createTable($this->table);
	}

	public function describe(): string {
		return 'create table '.$this->table->name;
	}
}
