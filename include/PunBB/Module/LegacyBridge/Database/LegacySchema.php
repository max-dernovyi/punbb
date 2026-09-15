<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Database;

use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\SchemaInterface;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\LegacyBridge\Layout\Markers;

/**
 * The schema through the DDL builders of the DBLayer include/essentials.php or
 * a setup route connected: each driver translates the types and refuses a
 * statement onto the forum's error page.
 */
final class LegacySchema implements SchemaInterface {
	public function tableExists(string $table): bool {
		return (bool) LegacyConnection::legacy()->table_exists($table);
	}

	public function fieldExists(string $table, string $field): bool {
		return (bool) LegacyConnection::legacy()->field_exists($table, $field);
	}

	public function indexExists(string $table, string $index): bool {
		return (bool) LegacyConnection::legacy()->index_exists($table, $index);
	}

	public function createTable(Table $table): void {
		LegacyConnection::legacy()->create_table($table->name, self::definition($table));
	}

	public function addField(string $table, Column $column, ?string $after = null): void {
		LegacyConnection::legacy()->add_field($table, $column->name, $column->type, $column->nullable, $column->default, $after);
	}

	public function alterField(string $table, Column $column, ?string $after = null): void {
		LegacyConnection::legacy()->alter_field($table, $column->name, $column->type, $column->nullable, $column->default, $after);
	}

	public function dropField(string $table, string $field): void {
		LegacyConnection::legacy()->drop_field($table, $field);
	}

	public function addIndex(string $table, string $index, array $columns, bool $unique = false): void {
		LegacyConnection::legacy()->add_index($table, $index, $columns, $unique);
	}

	public function dropIndex(string $table, string $index): void {
		LegacyConnection::legacy()->drop_index($table, $index);
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
			$definition['ENGINE'] = Markers::markup($table->engine);

		return $definition;
	}
}
