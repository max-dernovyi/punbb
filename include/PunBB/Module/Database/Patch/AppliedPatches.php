<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Patch;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;

/**
 * The applied patches, over the data_patches table the Database module declares.
 */
final class AppliedPatches implements AppliedPatchesInterface {
	public function __construct(private readonly Connection $db) {}

	public function names(): array {
		return array_map(static fn (Row $row): string => $row->string('name'), $this->db->select('SELECT p.name FROM '.$this->db->table('data_patches').' AS p'));
	}

	public function record(string $name): void {
		$this->db->execute('INSERT INTO '.$this->db->table('data_patches').' (name, applied) VALUES (?, ?)', $name, time());
	}
}
