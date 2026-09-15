<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Update\Api\ConversionInterface;
use PunBB\Module\Update\Api\Data\TextRowInterface;

/**
 * Rows of text over the forum's tables, and the columns the MySQL server
 * describes in information_schema.
 */
final class Conversion implements ConversionInterface {
	public function __construct(private readonly Connection $db) {}

	public function firstId(string $table): ?int {
		$id = $this->db->selectValue('SELECT r.id FROM '.$this->db->table($table).' AS r ORDER BY r.id LIMIT 1');

		return $id !== null ? (int) $id : null;
	}

	public function nextId(string $table, int $id): ?int {
		$next = $this->db->selectValue('SELECT r.id FROM '.$this->db->table($table).' AS r WHERE r.id >= ? ORDER BY r.id ASC LIMIT 1', $id);

		return $next !== null ? (int) $next : null;
	}

	public function rows(string $table, string $idColumn, array $columns, ?int $from = null, ?int $to = null): array {
		$platform = $this->db->platform();
		$id = $platform->quoteIdentifier($idColumn);
		$selected = array_map($platform->quoteIdentifier(...), $columns);

		$sql = 'SELECT '.$id.', '.implode(', ', $selected).' FROM '.$this->db->table($table);
		$parameters = array();

		if ($from !== null && $to !== null)
		{
			$sql .= ' WHERE '.$id.' >= ? AND '.$id.' < ?';
			$parameters = array($from, $to);
		}

		return array_map(static function (Row $row) use ($idColumn, $columns): TextRow {
			$values = array();
			foreach ($columns as $column)
				$values[$column] = $row->nullableString($column);

			return new TextRow($row->int($idColumn), $values);
		}, $this->db->select($sql.' ORDER BY '.$id, ...$parameters));
	}

	public function store(string $table, string $idColumn, TextRowInterface $row): void {
		$platform = $this->db->platform();
		$assignments = array();
		$values = array();

		foreach ($row->columns() as $column)
		{
			$assignments[] = $platform->quoteIdentifier($column).'=?';
			$values[] = $row->value($column);
		}

		$values[] = $row->id();

		$this->db->execute('UPDATE '.$this->db->table($table).' SET '.implode(', ', $assignments).' WHERE '.$platform->quoteIdentifier($idColumn).'=?', ...$values);
	}

	public function columns(string $table): array {
		return array_map(static fn (Row $row): TableColumn => new TableColumn(
			$row->string('column_name'),
			$row->string('column_type'),
			$row->nullableString('collation_name'),
			$row->string('is_nullable') === 'YES',
			$row->nullableString('column_default')
		), $this->db->select('SELECT c.COLUMN_NAME AS column_name, c.COLUMN_TYPE AS column_type, c.COLLATION_NAME AS collation_name, c.IS_NULLABLE AS is_nullable, c.COLUMN_DEFAULT AS column_default'.
			' FROM information_schema.COLUMNS AS c WHERE c.TABLE_SCHEMA=DATABASE() AND c.TABLE_NAME=? ORDER BY c.ORDINAL_POSITION', $this->db->prefix().$table));
	}

	public function setDefaultCharset(string $table): void {
		$this->db->execute('ALTER TABLE '.$this->db->table($table).' CHARACTER SET utf8');
	}
}
