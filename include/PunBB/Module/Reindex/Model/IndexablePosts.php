<?php

declare(strict_types=1);

namespace PunBB\Module\Reindex\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Reindex\Api\IndexablePostsInterface;

/**
 * The posts, read from the posts and topics tables.
 */
final class IndexablePosts implements IndexablePostsInterface {
	public function __construct(private readonly Connection $db) {}

	public function firstId(): ?int {
		$id = $this->db->selectValue('SELECT p.id FROM '.$this->db->table('posts').' AS p ORDER BY p.id LIMIT 1');

		return $id !== null ? (int) $id : null;
	}

	public function batch(int $startAt, int $limit): array {
		return array_map(static fn (Row $row): IndexablePost => new IndexablePost(
			$row->int('pid'),
			$row->nullableString('message') ?? '',
			$row->int('tid'),
			$row->string('subject'),
			$row->int('first_post_id')
		), $this->db->select('SELECT p.id AS pid, p.message, t.id AS tid, t.subject, t.first_post_id'.
			' FROM '.$this->db->table('posts').' AS p INNER JOIN '.$this->db->table('topics').' AS t ON t.id=p.topic_id'.
			' WHERE p.id >= ? ORDER BY p.id LIMIT ?', $startAt, $limit));
	}

	public function nextId(int $postId): ?int {
		$id = $this->db->selectValue('SELECT p.id FROM '.$this->db->table('posts').' AS p WHERE p.id > ? ORDER BY p.id LIMIT 1', $postId);

		return $id !== null ? (int) $id : null;
	}
}
