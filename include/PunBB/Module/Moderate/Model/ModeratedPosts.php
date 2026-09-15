<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Moderate\Api\Data\FirstPostInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedTopicInterface;
use PunBB\Module\Moderate\Api\Data\NewTopicInterface;
use PunBB\Module\Moderate\Api\ModeratedPostsInterface;

/**
 * A topic's posts, read from and written to the topics and posts tables with
 * their posters' users and groups.
 */
final class ModeratedPosts implements ModeratedPostsInterface {
	public function __construct(private readonly Connection $db) {}

	public function posterAddress(int $postId): ?string {
		$address = $this->db->selectValue('SELECT p.poster_ip FROM '.$this->db->table('posts').' AS p WHERE p.id=?', $postId);

		return $address !== null ? (string) $address : null;
	}

	public function topic(int $id, int $forumId): ?ModeratedTopicInterface {
		$row = $this->db->selectRow('SELECT t.subject, t.poster, t.first_post_id, t.posted, t.num_replies FROM '.$this->db->table('topics').' AS t WHERE t.id=? AND t.forum_id=? AND t.moved_to IS NULL', $id, $forumId);

		return $row !== null ? new ModeratedTopic($id, $row->string('subject'), $row->string('poster'), $row->int('first_post_id'), $row->int('posted'), $row->int('num_replies')) : null;
	}

	public function countReplies(int $topicId, int $firstPostId, int ...$postIds): int {
		if ($postIds === array())
			return 0;

		return (int) $this->db->selectValue('SELECT COUNT(p.id) FROM '.$this->db->table('posts').' AS p WHERE p.id IN('.self::list($postIds).') AND p.id!=? AND p.topic_id=?', $firstPostId, $topicId);
	}

	public function deletePosts(int ...$postIds): void {
		if ($postIds !== array())
			$this->db->execute('DELETE FROM '.$this->db->table('posts').' WHERE id IN('.self::list($postIds).')');
	}

	public function firstPost(int $id): ?FirstPostInterface {
		$row = $this->db->selectRow('SELECT p.id, p.poster, p.posted FROM '.$this->db->table('posts').' AS p WHERE p.id=?', $id);

		return $row !== null ? new FirstPost($row->int('id'), $row->string('poster'), $row->int('posted')) : null;
	}

	public function addTopics(NewTopicInterface ...$topics): void {
		foreach ($topics as $topic)
			$this->db->execute('INSERT INTO '.$this->db->table('topics').' (poster, subject, posted, first_post_id, forum_id) VALUES (?, ?, ?, ?, ?)',
				$topic->poster(), $topic->subject(), $topic->posted(), $topic->firstPostId(), $topic->forumId());
	}

	public function lastTopicId(): int {
		return $this->db->lastInsertId();
	}

	public function movePosts(int $topicId, int ...$postIds): void {
		if ($postIds !== array())
			$this->db->execute('UPDATE '.$this->db->table('posts').' SET topic_id=? WHERE id IN('.self::list($postIds).')', $topicId);
	}

	public function posts(int $topicId, int $offset, int $limit): array {
		return array_map(static fn (Row $row): ModeratedPost => new ModeratedPost(
			$row->int('id'),
			$row->string('poster'),
			$row->int('poster_id'),
			$row->nullableString('message') ?? '',
			$row->int('hide_smilies') === 1,
			$row->int('posted'),
			$row->nullableInt('edited'),
			$row->nullableString('edited_by') ?? '',
			$row->nullableString('title') ?? '',
			$row->int('num_posts'),
			$row->int('g_id'),
			$row->nullableString('g_user_title')
		), $this->db->select('SELECT u.title, u.num_posts, g.g_id, g.g_user_title, p.id, p.poster, p.poster_id, p.message, p.hide_smilies, p.posted, p.edited, p.edited_by'.
			' FROM '.$this->db->table('posts').' AS p INNER JOIN '.$this->db->table('users').' AS u ON u.id=p.poster_id INNER JOIN '.$this->db->table('groups').' AS g ON g.g_id=u.group_id'.
			' WHERE p.topic_id=? ORDER BY p.id LIMIT ? OFFSET ?', $topicId, $limit, $offset));
	}

	/**
	 * An id list for IN(): integers written into the statement, as a list
	 * posted can be longer than a statement takes parameters.
	 *
	 * @param array<int> $ids
	 */
	public static function list(array $ids): string {
		return implode(',', array_map(intval(...), $ids));
	}
}
