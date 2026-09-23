<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema;

use PunBB\Module\Database\Schema\Change\AddColumn;
use PunBB\Module\Database\Schema\Change\AddIndex;
use PunBB\Module\Database\Schema\Change\AlterColumn;
use PunBB\Module\Database\Schema\Change\ChangeInterface;
use PunBB\Module\Database\Schema\Change\CreateTable;
use PunBB\Module\Database\Schema\Change\DropColumn;
use PunBB\Module\Database\Schema\Change\DropIndex;
use PunBB\Module\Database\Sql\Platform;

/**
 * The changes that make an installed table what its module declares: a
 * missing table is created whole, a missing column, index or unique key is
 * added, a column of another type, nullability, default or collation is
 * altered, an index over other columns is dropped and added again, and a
 * column or index the table names as removed is dropped.
 *
 * What the declaration does not name is left alone — an extension's column in
 * a core table among it — and a primary key is made with its table only.
 * Types compare as the platform stores them: MySQL drops an integer's display
 * width, SQLite keeps only the affinity a type names.
 */
final class SchemaDiffer {
	/** @return list<ChangeInterface> in the order they are made */
	public function diff(Table $declared, ?InstalledTable $installed, Platform $platform): array {
		if ($installed === null)
			return array(new CreateTable($declared));

		$changes = array();

		$previous = null;
		foreach ($declared->columns as $column)
		{
			$present = $installed->column($column->name);
			if ($present === null)
				$changes[] = new AddColumn($declared->name, $column, $previous);
			else if (!self::same($column, $present, $platform))
				$changes[] = new AlterColumn($declared->name, $column);

			$previous = $column->name;
		}

		foreach ($declared->removedColumns as $column)
			if ($installed->column($column) !== null)
				$changes[] = new DropColumn($declared->name, $column);

		foreach ($declared->removedIndexes as $index)
			if ($installed->index($index) !== null)
				$changes[] = new DropIndex($declared->name, $index);

		foreach ($declared->uniqueKeys as $name => $columns)
			array_push($changes, ...self::index($declared->name, (string) $name, $columns, true, $installed));

		foreach ($declared->indexes as $name => $columns)
			array_push($changes, ...self::index($declared->name, (string) $name, $columns, false, $installed));

		return $changes;
	}

	private static function same(Column $declared, InstalledColumn $installed, Platform $platform): bool {
		return self::type($declared->type, $platform) === self::type($installed->type, $platform)
			&& $declared->nullable === $installed->nullable
			&& ($declared->default !== null ? (string) $declared->default : null) === $installed->default
			&& ($declared->collation === null || $installed->collation === null || str_ends_with($installed->collation, '_'.$declared->collation));
	}

	/**
	 * @param list<string> $columns
	 * @return list<ChangeInterface>
	 */
	private static function index(string $table, string $name, array $columns, bool $unique, InstalledTable $installed): array {
		$present = $installed->index($name);

		// PostgreSQL and SQLite name a unique key create_table() declares inline themselves
		if ($present === null && $unique)
			foreach ($installed->indexes as $index)
				if ($index->unique && $index->columns === $columns)
					return array();

		if ($present === null)
			return array(new AddIndex($table, $name, $columns, $unique));

		if ($present->columns === $columns && $present->unique === $unique)
			return array();

		return array(new DropIndex($table, $name), new AddIndex($table, $name, $columns, $unique));
	}

	/** $type, declared or reported, as $platform stores it. */
	private static function type(string $type, Platform $platform): string {
		$type = strtolower(trim((string) preg_replace('/\s+/', ' ', $type)));

		return match ($platform) {
			Platform::Mysql		=> self::mysqlType($type),
			Platform::Pgsql		=> self::pgsqlType($type),
			Platform::Sqlite	=> self::sqliteAffinity($type),
		};
	}

	/** The legacy mysqli layers create SERIAL as INT(10) UNSIGNED AUTO_INCREMENT; MySQL 8 reports an integer without its display width. */
	private static function mysqlType(string $type): string {
		if ($type === 'serial')
			return 'int unsigned';

		return (string) preg_replace(array('/^integer\b/', '/^(tinyint|smallint|mediumint|int|bigint) ?\(\d+\)/'), array('int', '$1'), $type);
	}

	/** A declared type as the legacy pgsql layer translates it, in the words format_type() reports it with. */
	private static function pgsqlType(string $type): string {
		return (string) preg_replace(array(
			'/^serial$/',
			'/^(tiny|small)int( ?\(\d+\))?( unsigned)?$/',
			'/^(medium)?int(eger)?( ?\(\d+\))?( unsigned)?$/',
			'/^bigint( ?\(\d+\))?( unsigned)?$/',
			'/^(tiny|medium|long)?text$/',
			'/^double( ?\([0-9,]+\))?( unsigned)?$/',
			'/^float( ?\(\d+\))?( unsigned)?$/',
			'/^varchar ?\((\d+)\)$/',
			'/^char ?\((\d+)\)$/',
		), array('integer', 'smallint', 'integer', 'bigint', 'text', 'double precision', 'real', 'character varying($1)', 'character($1)'), $type);
	}

	/** SQLite stores by the affinity a type names, whatever length it gives; the legacy layer declares SERIAL as INTEGER. */
	private static function sqliteAffinity(string $type): string {
		return match (true) {
			$type === 'serial', str_contains($type, 'int')											=> 'integer',
			str_contains($type, 'char'), str_contains($type, 'clob'), str_contains($type, 'text')	=> 'text',
			$type === '', str_contains($type, 'blob')												=> 'blob',
			str_contains($type, 'real'), str_contains($type, 'floa'), str_contains($type, 'doub')	=> 'real',
			default																					=> 'numeric',
		};
	}
}
