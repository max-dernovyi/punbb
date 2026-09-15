<?php

declare(strict_types=1);

namespace PunBB\Module\Ranks\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Ranks\Api\Data\RankInterface;
use PunBB\Module\Ranks\Api\RanksInterface;

/**
 * The ranks, read from and written to the ranks table, whose title column is named after a reserved word.
 */
final class Ranks implements RanksInterface {
	public function __construct(private readonly Connection $db) {}

	public function all(): array {
		return array_map(static fn (Row $row): Rank => new Rank($row->int('id'), $row->string('rank'), $row->int('min_posts')),
			$this->db->select('SELECT r.id, r.'.$this->rank().', r.min_posts FROM '.$this->db->table('ranks').' AS r ORDER BY r.min_posts'));
	}

	public function minPostsTaken(int $minPosts, ?int $exceptId): bool {
		return $exceptId === null
			? (int) $this->db->selectValue('SELECT COUNT(r.id) FROM '.$this->db->table('ranks').' AS r WHERE r.min_posts=?', $minPosts) > 0
			: (int) $this->db->selectValue('SELECT COUNT(r.id) FROM '.$this->db->table('ranks').' AS r WHERE r.id!=? AND r.min_posts=?', $exceptId, $minPosts) > 0;
	}

	public function add(RankInterface ...$ranks): void {
		foreach ($ranks as $rank)
			$this->db->execute('INSERT INTO '.$this->db->table('ranks').' ('.$this->rank().', min_posts) VALUES (?, ?)', $rank->title(), $rank->minPosts());
	}

	public function update(RankInterface ...$ranks): void {
		foreach ($ranks as $rank)
			$this->db->execute('UPDATE '.$this->db->table('ranks').' SET '.$this->rank().'=?, min_posts=? WHERE id=?', $rank->title(), $rank->minPosts(), $rank->id());
	}

	public function remove(int ...$ids): void {
		foreach ($ids as $id)
			$this->db->execute('DELETE FROM '.$this->db->table('ranks').' WHERE id=?', $id);
	}

	private function rank(): string {
		return $this->db->platform()->quoteIdentifier('rank');
	}
}
