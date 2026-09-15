<?php

declare(strict_types=1);

namespace PunBB\Module\Reports\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Reports\Api\ReportsInterface;

/**
 * The reports, read from the reports table with the posts, topics, forums and
 * users they name.
 */
final class Reports implements ReportsInterface {
	public function __construct(private readonly Connection $db) {}

	public function unread(): array {
		return array_map(self::report(...), $this->db->select('SELECT r.id, r.topic_id, r.forum_id, r.reported_by, r.created, r.message, p.id AS pid, t.subject, f.forum_name, u.username AS reporter'.
			' FROM '.$this->db->table('reports').' AS r'.
			' LEFT JOIN '.$this->db->table('posts').' AS p ON r.post_id=p.id'.
			' LEFT JOIN '.$this->db->table('topics').' AS t ON r.topic_id=t.id'.
			' LEFT JOIN '.$this->db->table('forums').' AS f ON r.forum_id=f.id'.
			' LEFT JOIN '.$this->db->table('users').' AS u ON r.reported_by=u.id'.
			' WHERE r.zapped IS NULL ORDER BY r.created DESC'));
	}

	public function recentlyRead(int $limit): array {
		return array_map(self::report(...), $this->db->select('SELECT r.id, r.topic_id, r.forum_id, r.reported_by, r.created, r.message, r.zapped, r.zapped_by AS zapped_by_id, p.id AS pid, t.subject, f.forum_name, u.username AS reporter, u2.username AS zapped_by'.
			' FROM '.$this->db->table('reports').' AS r'.
			' LEFT JOIN '.$this->db->table('posts').' AS p ON r.post_id=p.id'.
			' LEFT JOIN '.$this->db->table('topics').' AS t ON r.topic_id=t.id'.
			' LEFT JOIN '.$this->db->table('forums').' AS f ON r.forum_id=f.id'.
			' LEFT JOIN '.$this->db->table('users').' AS u ON r.reported_by=u.id'.
			' LEFT JOIN '.$this->db->table('users').' AS u2 ON r.zapped_by=u2.id'.
			' WHERE r.zapped IS NOT NULL ORDER BY r.zapped DESC LIMIT ?', $limit));
	}

	public function markRead(array $reportIds, int $userId, int $now): void {
		if ($reportIds === array())
			return;

		$this->db->execute('UPDATE '.$this->db->table('reports').' SET zapped=?, zapped_by=? WHERE id IN ('.implode(', ', array_fill(0, count($reportIds), '?')).') AND zapped IS NULL',
			$now, $userId, ...$reportIds);
	}

	private static function report(Row $row): Report {
		$values = $row->values();

		return new Report(
			$row->int('id'),
			$row->nullableInt('pid'),
			$row->int('topic_id'),
			$row->nullableString('subject'),
			$row->int('forum_id'),
			$row->nullableString('forum_name'),
			$row->int('reported_by'),
			$row->nullableString('reporter'),
			$row->int('created'),
			$row->nullableString('message') ?? '',
			array_key_exists('zapped', $values) ? $row->nullableInt('zapped') : null,
			array_key_exists('zapped_by_id', $values) ? $row->nullableInt('zapped_by_id') : null,
			array_key_exists('zapped_by', $values) ? $row->nullableString('zapped_by') : null
		);
	}
}
