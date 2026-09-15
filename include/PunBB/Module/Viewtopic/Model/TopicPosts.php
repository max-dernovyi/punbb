<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Viewtopic\Api\Data\PostLocationInterface;
use PunBB\Module\Viewtopic\Api\Data\ViewedTopicInterface;
use PunBB\Module\Viewtopic\Api\TopicPostsInterface;

/**
 * The topic and its posts, read from the topics, forums, forum permissions,
 * subscriptions, posts, users, groups and online tables.
 */
final class TopicPosts implements TopicPostsInterface {
	/** The account a guest posts under, which is never online. */
	public const GUEST = 1;

	public function __construct(private readonly Connection $db) {}

	public function locate(int $postId): ?PostLocationInterface {
		$row = $this->db->selectRow('SELECT p.topic_id, p.posted FROM '.$this->db->table('posts').' AS p WHERE p.id=?', $postId);

		return $row !== null ? new PostLocation($row->int('topic_id'), $row->int('posted')) : null;
	}

	public function countBefore(int $topicId, int $posted): int {
		return (int) $this->db->selectValue('SELECT COUNT(p.id) FROM '.$this->db->table('posts').' AS p WHERE p.topic_id=? AND p.posted<?', $topicId, $posted);
	}

	public function firstPostAfter(int $topicId, int $after): ?int {
		$id = $this->db->selectValue('SELECT MIN(p.id) FROM '.$this->db->table('posts').' AS p WHERE p.topic_id=? AND p.posted>?', $topicId, $after);

		return $id !== null ? (int) $id : null;
	}

	public function lastPostId(int $topicId): ?int {
		$id = $this->db->selectValue('SELECT t.last_post_id FROM '.$this->db->table('topics').' AS t WHERE t.id=?', $topicId);

		return $id !== null ? (int) $id : null;
	}

	public function topic(int $topicId, int $groupId, ?int $subscriberId): ?ViewedTopicInterface {
		$sql = 'SELECT t.subject, t.first_post_id, t.closed, t.num_replies, t.sticky, f.id AS forum_id, f.forum_name, f.moderators, fp.post_replies'.($subscriberId !== null ? ', s.user_id AS is_subscribed' : '').
			' FROM '.$this->db->table('topics').' AS t'.
			' INNER JOIN '.$this->db->table('forums').' AS f ON f.id=t.forum_id'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)';
		$parameters = array($groupId);

		if ($subscriberId !== null)
		{
			$sql .= ' LEFT JOIN '.$this->db->table('subscriptions').' AS s ON (t.id=s.topic_id AND s.user_id=?)';
			$parameters[] = $subscriberId;
		}

		$parameters[] = $topicId;
		$row = $this->db->selectRow($sql.' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.id=? AND t.moved_to IS NULL', ...$parameters);

		if ($row === null)
			return null;

		return new ViewedTopic(
			$topicId,
			$row->string('subject'),
			$row->int('first_post_id'),
			$row->int('closed') === 1,
			$row->int('sticky') === 1,
			$row->int('num_replies'),
			$row->int('forum_id'),
			$row->string('forum_name'),
			Moderator::listOf($row->nullableString('moderators')),
			$row->nullableInt('post_replies') !== null ? $row->nullableInt('post_replies') === 1 : null,
			$subscriberId !== null && $row->nullableInt('is_subscribed') !== null
		);
	}

	public function postIds(int $topicId, int $offset, int $limit): array {
		return array_map(static fn (Row $row): int => $row->int('id'),
			$this->db->select('SELECT p.id FROM '.$this->db->table('posts').' AS p WHERE p.topic_id=? ORDER BY p.id LIMIT ? OFFSET ?', $topicId, $limit, $offset));
	}

	public function posts(array $postIds): array {
		if ($postIds === array())
			return array();

		$rows = $this->db->select('SELECT u.email, u.title, u.url, u.location, u.signature, u.email_setting, u.num_posts, u.registered, u.admin_note, u.avatar, u.avatar_width, u.avatar_height,'.
			' p.id, p.poster AS username, p.poster_id, p.poster_ip, p.poster_email, p.message, p.hide_smilies, p.posted, p.edited, p.edited_by, g.g_id, g.g_user_title, o.user_id AS is_online'.
			' FROM '.$this->db->table('posts').' AS p'.
			' INNER JOIN '.$this->db->table('users').' AS u ON u.id=p.poster_id'.
			' INNER JOIN '.$this->db->table('groups').' AS g ON g.g_id=u.group_id'.
			' LEFT JOIN '.$this->db->table('online').' AS o ON (o.user_id=u.id AND o.user_id!=? AND o.idle=0)'.
			' WHERE p.id IN ('.implode(', ', array_fill(0, count($postIds), '?')).') ORDER BY p.id', self::GUEST, ...$postIds);

		return array_map(self::post(...), $rows);
	}

	public function countView(int ...$topicIds): void {
		foreach ($topicIds as $topicId)
			$this->db->execute('UPDATE '.$this->db->table('topics').' SET num_views=num_views+1 WHERE id=?', $topicId);
	}

	private static function post(Row $row): TopicPost {
		return new TopicPost(
			$row->int('id'),
			$row->int('poster_id'),
			$row->string('username'),
			$row->nullableString('poster_ip') ?? '',
			$row->nullableString('poster_email'),
			$row->nullableString('message') ?? '',
			$row->int('hide_smilies') === 1,
			$row->int('posted'),
			$row->nullableInt('edited'),
			$row->nullableString('edited_by'),
			$row->string('email'),
			$row->nullableString('title'),
			$row->nullableString('url'),
			$row->nullableString('location'),
			$row->nullableString('signature'),
			$row->int('email_setting'),
			$row->int('num_posts'),
			$row->int('registered'),
			$row->nullableString('admin_note'),
			$row->int('avatar'),
			$row->int('avatar_width'),
			$row->int('avatar_height'),
			$row->int('g_id'),
			$row->nullableString('g_user_title'),
			$row->nullableInt('is_online') !== null && $row->nullableInt('is_online') === $row->int('poster_id')
		);
	}
}
