<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema;

use DBLayer;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Platform;

/**
 * The schema through the DDL builders of a DBLayer driver: each driver
 * translates the types and refuses a statement onto the forum's error page.
 * $connection is the same database, read through its catalogue.
 */
final class DbLayerSchema implements SchemaInterface {
	public function __construct(
		private readonly DBLayer $db,
		private readonly Connection $connection
	) {}

	public function describe(string $table): ?InstalledTable {
		return (new SchemaReader($this->connection))->table($table);
	}

	public function tableExists(string $table): bool {
		return (bool) $this->db->table_exists($table);
	}

	public function fieldExists(string $table, string $field): bool {
		return (bool) $this->db->field_exists($table, $field);
	}

	public function indexExists(string $table, string $index): bool {
		return (bool) $this->db->index_exists($table, $index);
	}

	public function createTable(Table $table): void {
		$this->db->create_table($table->name, self::definition($table));
	}

	public function addField(string $table, Column $column, ?string $after = null): void {
		$this->db->add_field($table, $column->name, $this->type($column), $column->nullable, $column->default, $after);
	}

	public function alterField(string $table, Column $column, ?string $after = null): void {
		$this->db->alter_field($table, $column->name, $this->type($column), $column->nullable, $column->default, $after);
	}

	public function dropField(string $table, string $field): void {
		$this->db->drop_field($table, $field);
	}

	public function addIndex(string $table, string $index, array $columns, bool $unique = false): void {
		$this->db->add_index($table, $index, $columns, $unique);
	}

	public function dropIndex(string $table, string $index): void {
		$this->db->drop_index($table, $index);
	}

	/** $column's type, and its collation where MySQL takes one: create_table() adds it itself, add_field() and alter_field() do not. */
	private function type(Column $column): string {
		if ($column->collation === null || $this->connection->platform() !== Platform::Mysql)
			return $column->type;

		return $column->type.' CHARACTER SET utf8 COLLATE utf8_'.$column->collation;
	}

	/**
	 * $table as the array create_table() takes: a text default is a quoted
	 * literal there, where add_field() quotes it itself.
	 *
	 * @return array<string, mixed>
	 */
	public static function definition(Table $table): array {
		$fields = array();
		foreach ($table->columns as $column)
		{
			$field = array('datatype' => $column->type, 'allow_null' => $column->nullable);

			if (is_int($column->default))
				$field['default'] = (string) $column->default;
			else if (is_string($column->default))
				$field['default'] = '\''.str_replace('\'', '\'\'', $column->default).'\'';

			if ($column->collation !== null)
				$field['collation'] = $column->collation;

			$fields[$column->name] = $field;
		}

		$definition = array('FIELDS' => $fields);

		if ($table->primaryKey !== array())
			$definition['PRIMARY KEY'] = $table->primaryKey;

		if ($table->uniqueKeys !== array())
			$definition['UNIQUE KEYS'] = $table->uniqueKeys;

		if ($table->indexes !== array())
			$definition['INDEXES'] = $table->indexes;

		if ($table->engine !== null)
			$definition['ENGINE'] = $table->engine;

		return $definition;
	}
}
