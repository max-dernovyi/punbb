<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema;

/**
 * The structure of the forum's database. Table names are unprefixed. Each
 * change is made only when it is not made already: a table that exists is not
 * created, a column that is missing is not altered.
 */
interface SchemaInterface {
	/** Table $table as the database reports it; null when there is no such table. */
	public function describe(string $table): ?InstalledTable;

	public function tableExists(string $table): bool;

	public function fieldExists(string $table, string $field): bool;

	public function indexExists(string $table, string $index): bool;

	public function createTable(Table $table): void;

	/** Adds $column to $table, after column $after where the database places columns. */
	public function addField(string $table, Column $column, ?string $after = null): void;

	/** Changes column $column->name of $table into $column. */
	public function alterField(string $table, Column $column, ?string $after = null): void;

	public function dropField(string $table, string $field): void;

	/** @param list<string> $columns */
	public function addIndex(string $table, string $index, array $columns, bool $unique = false): void;

	public function dropIndex(string $table, string $index): void;
}
