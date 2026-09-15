<?php

declare(strict_types=1);

namespace PunBB\Module\Delete\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Delete\Api\Data\DeletablePostInterface;
use PunBB\Module\Delete\Api\DeletablePostsInterface;

/**
 * The posts, read from the posts, topics and forums tables with the reading
 * permission of the group asking.
 */
final class DeletablePosts implements DeletablePostsInterface {
	public function __construct(private readonly Connection $db) {}

	public function find(int $postId, int $groupId): ?DeletablePostInterface {
		$row = $this->db->selectRow('SELECT f.id AS fid, f.forum_name, f.moderators, t.id AS tid, t.subject, t.first_post_id, t.closed, p.poster, p.poster_id, p.message, p.hide_smilies, p.posted'.
			' FROM '.$this->db->table('posts').' AS p'.
			' INNER JOIN '.$this->db->table('topics').' AS t ON t.id=p.topic_id'.
			' INNER JOIN '.$this->db->table('forums').' AS f ON f.id=t.forum_id'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
			' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND p.id=?', $groupId, $postId);

		if ($row === null)
			return null;

		return new DeletablePost(
			$postId,
			$row->int('fid'),
			$row->string('forum_name'),
			Moderator::listOf($row->nullableString('moderators')),
			$row->int('tid'),
			$row->string('subject'),
			$row->int('first_post_id'),
			$row->int('closed') === 1,
			$row->string('poster'),
			$row->int('poster_id'),
			$row->nullableString('message') ?? '',
			$row->int('hide_smilies') === 1,
			$row->int('posted')
		);
	}

	public function previousPostId(int $topicId, int $postId): ?int {
		$id = $this->db->selectValue('SELECT p.id FROM '.$this->db->table('posts').' AS p WHERE p.topic_id=? AND p.id<? ORDER BY p.id DESC LIMIT 1', $topicId, $postId);

		return $id !== null ? (int) $id : null;
	}
}
