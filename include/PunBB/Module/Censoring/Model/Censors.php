<?php

declare(strict_types=1);

namespace PunBB\Module\Censoring\Model;

use PunBB\Module\Censoring\Api\CensorsInterface;
use PunBB\Module\Censoring\Api\Data\CensorInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;

/**
 * The censored words, read from and written to the censoring table.
 */
final class Censors implements CensorsInterface {
	public function __construct(private readonly Connection $db) {}

	public function all(): array {
		return array_map(static fn (Row $row): Censor => new Censor($row->int('id'), $row->string('search_for'), $row->string('replace_with')),
			$this->db->select('SELECT c.id, c.search_for, c.replace_with FROM '.$this->db->table('censoring').' AS c ORDER BY c.search_for'));
	}

	public function add(CensorInterface ...$censors): void {
		foreach ($censors as $censor)
			$this->db->execute('INSERT INTO '.$this->db->table('censoring').' (search_for, replace_with) VALUES (?, ?)', $censor->searchFor(), $censor->replaceWith());
	}

	public function update(CensorInterface ...$censors): void {
		foreach ($censors as $censor)
			$this->db->execute('UPDATE '.$this->db->table('censoring').' SET search_for=?, replace_with=? WHERE id=?', $censor->searchFor(), $censor->replaceWith(), $censor->id());
	}

	public function remove(int ...$ids): void {
		foreach ($ids as $id)
			$this->db->execute('DELETE FROM '.$this->db->table('censoring').' WHERE id=?', $id);
	}
}
