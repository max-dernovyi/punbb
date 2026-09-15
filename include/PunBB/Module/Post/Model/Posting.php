<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Post\Api\Data\LocationInterface;
use PunBB\Module\Post\Api\Data\QuoteInterface;
use PunBB\Module\Post\Api\PostingInterface;

/**
 * Where members post, read from the topics and forums tables with the
 * permissions of the group asking, and the posts quoted and reviewed.
 */
final class Posting implements PostingInterface {
	public function __construct(private readonly Connection $db) {}

	public function topic(int $topicId, int $groupId, int $userId): ?LocationInterface {
		$row = $this->db->selectRow('SELECT f.id, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics, t.subject, t.closed, s.user_id AS is_subscribed'.
			' FROM '.$this->db->table('topics').' AS t'.
			' INNER JOIN '.$this->db->table('forums').' AS f ON f.id=t.forum_id'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
			' LEFT JOIN '.$this->db->table('subscriptions').' AS s ON (t.id=s.topic_id AND s.user_id=?)'.
			' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.id=?', $groupId, $userId, $topicId);

		return $row !== null ? self::location($row, $topicId, $row->string('subject'), $row->int('closed') === 1, $row->nullableInt('is_subscribed') !== null) : null;
	}

	public function forum(int $forumId, int $groupId): ?LocationInterface {
		$row = $this->db->selectRow('SELECT f.id, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics'.
			' FROM '.$this->db->table('forums').' AS f'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
			' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id=?', $groupId, $forumId);

		return $row !== null ? self::location($row) : null;
	}

	public function quote(int $postId, int $topicId): ?QuoteInterface {
		$row = $this->db->selectRow('SELECT p.poster, p.message FROM '.$this->db->table('posts').' AS p WHERE id=? AND topic_id=?', $postId, $topicId);

		return $row !== null ? new Quote($row->string('poster'), $row->nullableString('message') ?? '') : null;
	}

	public function reviewCount(int $topicId): int {
		return (int) $this->db->selectValue('SELECT count(p.id) FROM '.$this->db->table('posts').' AS p WHERE topic_id=?', $topicId);
	}

	public function review(int $topicId, int $limit): array {
		return array_map(static fn (Row $row): ReviewPost => new ReviewPost($row->int('id'), $row->string('poster'), $row->nullableString('message') ?? '', $row->int('hide_smilies') === 1, $row->int('posted')),
			$this->db->select('SELECT p.id, p.poster, p.message, p.hide_smilies, p.posted FROM '.$this->db->table('posts').' AS p WHERE topic_id=? ORDER BY id DESC LIMIT ?', $topicId, $limit));
	}

	private static function location(Row $row, int $topicId = 0, string $subject = '', bool $closed = false, bool $subscribed = false): Location {
		return new Location(
			$row->int('id'),
			$row->string('forum_name'),
			Moderator::listOf($row->nullableString('moderators')),
			$row->nullableString('redirect_url') ?? '',
			self::permission($row->nullableInt('post_replies')),
			self::permission($row->nullableInt('post_topics')),
			$topicId,
			$subject,
			$closed,
			$subscribed
		);
	}

	private static function permission(?int $stored): ?bool {
		return $stored !== null ? $stored === 1 : null;
	}
}
