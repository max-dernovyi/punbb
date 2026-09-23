<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Version;

use PunBB\Module\Database\Schema\SchemaReader;
use PunBB\Module\Database\Sql\Connection;

/**
 * The recorded module versions, over the modules table the Database module
 * declares. A version recorded alone leaves the other at its column's zero.
 */
final class InstalledVersions implements InstalledVersionsInterface {
	public function __construct(
		private readonly Connection $db,
		private readonly SchemaReader $catalogue
	) {}

	public function all(): array {
		// A board from before module versions has no table to read them from
		if ($this->catalogue->table('modules') === null)
			return array();

		$versions = array();
		foreach ($this->db->select('SELECT m.name, m.schema_version, m.data_version FROM '.$this->db->table('modules').' AS m') as $row)
			$versions[$row->string('name')] = new InstalledVersion($row->string('schema_version'), $row->string('data_version'));

		return $versions;
	}

	public function recordSchema(string $module, string $version): void {
		$this->record($module, 'schema_version', $version);
	}

	public function recordData(string $module, string $version): void {
		$this->record($module, 'data_version', $version);
	}

	private function record(string $module, string $column, string $version): void {
		$table = $this->db->table('modules');

		if ($this->db->selectValue('SELECT 1 FROM '.$table.' AS m WHERE m.name = ?', $module) === null)
			$this->db->execute('INSERT INTO '.$table.' (name, '.$column.') VALUES (?, ?)', $module, $version);
		else
			$this->db->execute('UPDATE '.$table.' SET '.$column.' = ? WHERE name = ?', $version, $module);
	}
}
