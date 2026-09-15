<?php

declare(strict_types=1);

namespace PunBB\Module\Prune\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Prune\Api\PrunableTopicsInterface;

/**
 * The forums and their topics, read from the categories, forums and topics tables.
 */
final class PrunableTopics implements PrunableTopicsInterface {
	public function __construct(private readonly Connection $db) {}

	public function forums(): array {
		return array_map(static fn (Row $row): PrunableForum => new PrunableForum($row->int('cid'), $row->string('cat_name'), $row->int('fid'), $row->string('forum_name')),
			$this->db->select('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name'.
				' FROM '.$this->db->table('categories').' AS c INNER JOIN '.$this->db->table('forums').' AS f ON c.id=f.cat_id'.
				' WHERE f.redirect_url IS NULL ORDER BY c.disp_position, c.id, f.disp_position'));
	}

	public function forumIds(): array {
		return array_map(static fn (Row $row): int => $row->int('id'), $this->db->select('SELECT f.id FROM '.$this->db->table('forums').' AS f ORDER BY f.id'));
	}

	public function forumName(int $forumId): ?string {
		$name = $this->db->selectValue('SELECT f.forum_name FROM '.$this->db->table('forums').' AS f WHERE f.id=?', $forumId);

		return $name !== null ? (string) $name : null;
	}

	public function count(?int $forumId, int $lastPostBefore, bool $sticky): int {
		$sql = 'SELECT COUNT(t.id) FROM '.$this->db->table('topics').' AS t WHERE t.last_post<? AND t.moved_to IS NULL';
		$parameters = array($lastPostBefore);

		if ($forumId !== null)
		{
			$sql .= ' AND t.forum_id=?';
			$parameters[] = $forumId;
		}

		if (!$sticky)
			$sql .= ' AND t.sticky=0';

		return (int) $this->db->selectValue($sql, ...$parameters);
	}
}
