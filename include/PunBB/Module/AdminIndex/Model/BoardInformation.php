<?php

declare(strict_types=1);

namespace PunBB\Module\AdminIndex\Model;

use PunBB\Module\AdminIndex\Api\BoardInformationInterface;
use PunBB\Module\AdminIndex\Api\Data\DatabaseInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Database\Sql\Row;

/**
 * The board's figures, read from the online and extensions tables, and the
 * server's own account of itself.
 */
final class BoardInformation implements BoardInformationInterface {
	public function __construct(private readonly Connection $db) {}

	public function onlineCount(): int {
		return (int) $this->db->selectValue('SELECT COUNT(o.user_id) FROM '.$this->db->table('online').' AS o WHERE o.idle=?', 0);
	}

	public function hotfixes(): array {
		return array_map(static fn (Row $row): string => $row->string('id'),
			$this->db->select('SELECT e.id FROM '.$this->db->table('extensions').' AS e WHERE e.id LIKE ?', 'hotfix_%'));
	}

	public function database(): DatabaseInterface {
		return match ($this->db->platform()) {
			Platform::Mysql		=> $this->mysql(),
			Platform::Pgsql		=> new Database('PostgreSQL', (string) preg_replace('/^[^0-9]+([^\s,-]+).*$/', '\1', (string) $this->db->selectValue('SELECT VERSION()')), null, null),
			Platform::Sqlite	=> new Database('SQLite3', (string) $this->db->selectValue('SELECT sqlite_version()'), null, null),
		};
	}

	private function mysql(): Database {
		$version = (string) preg_replace('/^([^-]+).*$/', '\1', (string) $this->db->selectValue('SELECT VERSION()'));

		$tables = $this->db->selectRow('SELECT COALESCE(SUM(t.TABLE_ROWS), 0) AS table_rows, COALESCE(SUM(t.DATA_LENGTH + t.INDEX_LENGTH), 0) AS table_size'.
			' FROM information_schema.TABLES AS t WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_NAME LIKE ?', $this->db->prefix().'%');

		return new Database('MySQL', $version, $tables?->int('table_rows') ?? 0, $tables?->int('table_size') ?? 0);
	}
}
