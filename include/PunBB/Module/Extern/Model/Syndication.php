<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Extern\Api\Data\FeedTopicInterface;
use PunBB\Module\Extern\Api\Data\StatisticsInterface;
use PunBB\Module\Extern\Api\SyndicationInterface;

/**
 * What the board syndicates, read from the topics, posts, users, forums,
 * forum permissions and online tables.
 */
final class Syndication implements SyndicationInterface {
	/** The group of accounts that have not confirmed their address. */
	private const UNVERIFIED_GROUP = 0;

	public function __construct(private readonly Connection $db) {}

	public function topic(int $topicId, int $groupId): ?FeedTopicInterface {
		$row = $this->db->selectRow('SELECT t.subject, t.first_post_id FROM '.$this->db->table('topics').' AS t'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=t.forum_id AND fp.group_id=?)'.
			' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.moved_to IS NULL AND t.id=?', $groupId, $topicId);

		return $row !== null ? new FeedTopic($topicId, $row->string('subject'), $row->int('first_post_id')) : null;
	}

	public function posts(int $topicId, int $limit): array {
		return array_map(static fn (Row $row): FeedEntry => self::entry($row, $row->int('id'), ''),
			$this->db->select('SELECT p.id, p.poster, p.message, p.hide_smilies, p.posted, p.poster_id, u.email_setting, u.email, p.poster_email'.
				' FROM '.$this->db->table('posts').' AS p INNER JOIN '.$this->db->table('users').' AS u ON u.id = p.poster_id'.
				' WHERE p.topic_id=? ORDER BY p.posted DESC LIMIT ?', $topicId, $limit));
	}

	public function forumName(int $forumId, int $groupId): ?string {
		$name = $this->db->selectValue('SELECT f.forum_name FROM '.$this->db->table('forums').' AS f'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
			' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id=?', $groupId, $forumId);

		return $name !== null ? (string) $name : null;
	}

	public function topics(int $groupId, array $forumIds, bool $excluding, bool $byLastPost, int $limit): array {
		$sql = 'SELECT t.id, t.poster, t.posted, t.subject, p.message, p.hide_smilies, u.email_setting, u.email, p.poster_id, p.poster_email'.
			' FROM '.$this->db->table('topics').' AS t'.
			' INNER JOIN '.$this->db->table('posts').' AS p ON p.id = t.first_post_id'.
			' INNER JOIN '.$this->db->table('users').' AS u ON u.id = p.poster_id'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id = t.forum_id AND fp.group_id = ?)'.
			' WHERE (fp.read_forum IS NULL OR fp.read_forum = 1) AND t.moved_to IS NULL';
		$parameters = array($groupId);

		if ($forumIds !== array())
		{
			$sql .= ' AND t.forum_id '.($excluding ? 'NOT IN' : 'IN').' ('.implode(', ', array_fill(0, count($forumIds), '?')).')';
			array_push($parameters, ...$forumIds);
		}

		$parameters[] = $limit;

		return array_map(static fn (Row $row): FeedEntry => self::entry($row, $row->int('id'), $row->string('subject')),
			$this->db->select($sql.' ORDER BY '.($byLastPost ? 't.last_post' : 't.posted').' DESC LIMIT ?', ...$parameters));
	}

	public function onlineVisitors(): array {
		return array_map(static fn (Row $row): OnlineVisitor => new OnlineVisitor($row->int('user_id'), $row->string('ident')),
			$this->db->select('SELECT o.user_id, o.ident FROM '.$this->db->table('online').' AS o WHERE o.idle=? ORDER BY o.ident', 0));
	}

	public function statistics(): StatisticsInterface {
		$users = (int) $this->db->selectValue('SELECT COUNT(u.id) - 1 FROM '.$this->db->table('users').' AS u WHERE u.group_id != ?', self::UNVERIFIED_GROUP);
		$newest = $this->db->selectRow('SELECT u.id, u.username FROM '.$this->db->table('users').' AS u WHERE u.group_id != ? ORDER BY u.registered DESC LIMIT 1', self::UNVERIFIED_GROUP);
		$posts = $this->db->selectRow('SELECT SUM(f.num_topics) AS num_topics, SUM(f.num_posts) AS num_posts FROM '.$this->db->table('forums').' AS f');

		return new Statistics($users, $newest?->int('id') ?? 0, $newest?->string('username') ?? '', $posts?->nullableInt('num_topics') ?? 0, $posts?->nullableInt('num_posts') ?? 0);
	}

	private static function entry(Row $row, int $id, string $subject): FeedEntry {
		return new FeedEntry(
			$id,
			$subject,
			$row->string('poster'),
			$row->int('poster_id'),
			$row->int('posted'),
			$row->nullableString('message') ?? '',
			$row->int('hide_smilies') === 1,
			$row->nullableString('email') ?? '',
			$row->nullableInt('email_setting') === 0,
			$row->nullableString('poster_email') ?? ''
		);
	}
}
