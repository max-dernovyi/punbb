<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Viewforum\Api\ForumTopicsInterface;
use PunBB\Module\Viewforum\Api\Data\ViewedForumInterface;

/**
 * The forum and its topics, read from the forums, forum permissions,
 * subscriptions, topics and posts tables.
 */
final class ForumTopics implements ForumTopicsInterface {
	/** The columns of a listed topic; a query that also asks who posted groups by every one of them. */
	private const TOPIC_COLUMNS = 't.id, t.poster, t.subject, t.posted, t.first_post_id, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to';

	public function __construct(private readonly Connection $db) {}

	public function forum(int $forumId, int $groupId, ?int $subscriberId): ?ViewedForumInterface {
		$sql = 'SELECT f.forum_name, f.redirect_url, f.moderators, f.num_topics, f.sort_by, fp.post_topics, f.forum_desc'.($subscriberId !== null ? ', fs.user_id AS is_subscribed' : '').
			' FROM '.$this->db->table('forums').' AS f'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)';
		$parameters = array($groupId);

		if ($subscriberId !== null)
		{
			$sql .= ' LEFT JOIN '.$this->db->table('forum_subscriptions').' AS fs ON (f.id=fs.forum_id AND fs.user_id=?)';
			$parameters[] = $subscriberId;
		}

		$parameters[] = $forumId;
		$row = $this->db->selectRow($sql.' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id=?', ...$parameters);

		if ($row === null)
			return null;

		return new ViewedForum(
			$forumId,
			$row->string('forum_name'),
			$row->nullableString('forum_desc') ?? '',
			$row->nullableString('redirect_url') ?? '',
			Moderator::listOf($row->nullableString('moderators')),
			$row->int('num_topics'),
			$row->int('sort_by') === 1,
			$row->nullableInt('post_topics') !== null ? $row->nullableInt('post_topics') === 1 : null,
			$subscriberId !== null && $row->nullableInt('is_subscribed') !== null
		);
	}

	public function topicIds(int $forumId, bool $byPosted, int $offset, int $limit): array {
		return array_map(static fn (Row $row): int => $row->int('id'),
			$this->db->select('SELECT t.id FROM '.$this->db->table('topics').' AS t WHERE t.forum_id=?'.
				' ORDER BY t.sticky DESC, '.($byPosted ? 't.posted' : 't.last_post').' DESC LIMIT ? OFFSET ?', $forumId, $limit, $offset));
	}

	public function topics(array $topicIds, bool $byPosted, ?int $posterId): array {
		if ($topicIds === array())
			return array();

		$in = implode(', ', array_fill(0, count($topicIds), '?'));
		$order = ' ORDER BY t.sticky DESC, '.($byPosted ? 't.posted' : 't.last_post').' DESC';

		$rows = $posterId === null
			? $this->db->select('SELECT '.self::TOPIC_COLUMNS.' FROM '.$this->db->table('topics').' AS t WHERE t.id IN ('.$in.')'.$order, ...$topicIds)
			: $this->db->select('SELECT '.self::TOPIC_COLUMNS.', p.poster_id AS has_posted FROM '.$this->db->table('topics').' AS t'.
				' LEFT JOIN '.$this->db->table('posts').' AS p ON (p.poster_id=? AND p.topic_id=t.id)'.
				' WHERE t.id IN ('.$in.') GROUP BY '.self::TOPIC_COLUMNS.', p.poster_id'.$order, $posterId, ...$topicIds);

		return array_map(static fn (Row $row): ListedTopic => new ListedTopic(
			$row->int('id'),
			$row->string('poster'),
			$row->string('subject'),
			$row->int('posted'),
			$row->int('first_post_id'),
			$row->nullableInt('last_post') ?? 0,
			$row->nullableInt('last_post_id') ?? 0,
			$row->nullableString('last_poster') ?? '',
			$row->int('num_views'),
			$row->int('num_replies'),
			$row->int('closed') === 1,
			$row->int('sticky') === 1,
			$row->nullableInt('moved_to'),
			$posterId !== null && $row->nullableInt('has_posted') === $posterId
		), $rows);
	}
}
