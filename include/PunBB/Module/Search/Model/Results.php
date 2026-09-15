<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Search\Api\ResultsInterface;

/**
 * The results, read from the posts, topics, forums, categories, subscription
 * and forum permissions tables.
 */
final class Results implements ResultsInterface {
	/** The columns of a post found. */
	private const POST_COLUMNS = 'p.id AS pid, p.poster AS pposter, p.posted AS pposted, p.poster_id, p.message, p.hide_smilies, t.id AS tid, t.poster, t.subject, t.first_post_id, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.forum_id, f.forum_name';

	/** The columns of a topic found; a query that also asks who posted groups by every one of them. */
	private const TOPIC_COLUMNS = 't.id AS tid, t.poster, t.subject, t.first_post_id, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.sticky, t.forum_id, f.forum_name';

	private const TOPIC_GROUP = 't.id, t.poster, t.subject, t.first_post_id, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.sticky, t.forum_id, f.forum_name';

	private const READABLE = '(fp.read_forum IS NULL OR fp.read_forum=1)';

	public function __construct(private readonly Connection $db) {}

	public function posts(array $postIds, ?int $sortBy, string $sortDir): array {
		if ($postIds === array())
			return array();

		$sort = match ($sortBy) {
			1		=> 'p.poster',
			2		=> 't.subject',
			3		=> 't.forum_id',
			default	=> 'p.posted',
		};

		return $this->postsOf($this->db->select('SELECT '.self::POST_COLUMNS.' FROM '.$this->db->table('posts').' AS p'.
			' INNER JOIN '.$this->db->table('topics').' AS t ON t.id=p.topic_id'.
			' INNER JOIN '.$this->db->table('forums').' AS f ON f.id=t.forum_id'.
			' WHERE p.id IN('.Searches::list($postIds).') ORDER BY '.$sort.' '.self::direction($sortDir)));
	}

	public function topics(array $topicIds, ?int $sortBy, string $sortDir, ?int $posterId): array {
		if ($topicIds === array())
			return array();

		$sort = match ($sortBy) {
			1		=> 't.poster',
			2		=> 't.subject',
			3		=> 't.forum_id',
			default	=> 't.posted',
		};

		return $this->topicList('', array(), null, $posterId, 'WHERE t.id IN('.Searches::list($topicIds).')', array(), $sort.' '.self::direction($sortDir));
	}

	public function newTopics(int $groupId, int $since, ?int $forumId, ?int $posterId): array {
		$parameters = array($since);
		$where = 'WHERE '.self::READABLE.' AND t.last_post>? AND t.moved_to IS NULL';

		if ($forumId !== null)
		{
			$where .= ' AND f.id=?';
			$parameters[] = $forumId;
		}

		return $this->topicList('', array(), $groupId, $posterId, $where, $parameters, 't.last_post DESC');
	}

	public function recentTopics(int $groupId, int $since, ?int $posterId): array {
		return $this->topicList('', array(), $groupId, $posterId, 'WHERE '.self::READABLE.' AND t.last_post>? AND t.moved_to IS NULL', array($since), 't.last_post DESC');
	}

	public function userPosts(int $groupId, int $userId): array {
		return $this->postsOf($this->db->select('SELECT '.self::POST_COLUMNS.' FROM '.$this->db->table('posts').' AS p'.
			' INNER JOIN '.$this->db->table('topics').' AS t ON t.id=p.topic_id'.
			' INNER JOIN '.$this->db->table('forums').' AS f ON f.id=t.forum_id'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
			' WHERE '.self::READABLE.' AND p.poster_id=? ORDER BY pposted DESC', $groupId, $userId));
	}

	public function userTopics(int $groupId, int $userId, ?int $posterId): array {
		$sql = 'SELECT '.self::TOPIC_COLUMNS.($posterId !== null ? ', ps.poster_id AS has_posted' : '').' FROM '.$this->db->table('topics').' AS t'.
			' INNER JOIN '.$this->db->table('posts').' AS p ON t.first_post_id=p.id'.
			' INNER JOIN '.$this->db->table('forums').' AS f ON f.id=t.forum_id'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)';
		$parameters = array($groupId);

		if ($posterId !== null)
		{
			$sql .= ' LEFT JOIN '.$this->db->table('posts').' AS ps ON (ps.poster_id=? AND ps.topic_id=t.id)';
			$parameters[] = $posterId;
		}

		$parameters[] = $userId;
		$sql .= ' WHERE '.self::READABLE.' AND p.poster_id=?'.($posterId !== null ? ' GROUP BY '.self::TOPIC_GROUP.', ps.poster_id' : '').' ORDER BY t.last_post DESC';

		return $this->topicsOf($this->db->select($sql, ...$parameters), $posterId);
	}

	public function subscribedTopics(int $groupId, int $userId, ?int $posterId): array {
		return $this->topicList(' INNER JOIN '.$this->db->table('subscriptions').' AS s ON (t.id=s.topic_id AND s.user_id=?)', array($userId), $groupId, $posterId, 'WHERE '.self::READABLE, array(), 't.last_post DESC');
	}

	public function subscribedForums(int $groupId, int $userId): array {
		$rows = $this->db->select('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster'.
			' FROM '.$this->db->table('categories').' AS c'.
			' INNER JOIN '.$this->db->table('forums').' AS f ON c.id=f.cat_id'.
			' INNER JOIN '.$this->db->table('forum_subscriptions').' AS fs ON (f.id=fs.forum_id AND fs.user_id=?)'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
			' WHERE '.self::READABLE.' ORDER BY c.disp_position, c.id, f.disp_position', $userId, $groupId);

		return array_map(static fn (Row $row): ResultForum => new ResultForum(
			$row->int('cid'),
			$row->string('cat_name'),
			$row->int('fid'),
			$row->string('forum_name'),
			$row->nullableString('forum_desc') ?? '',
			$row->nullableString('redirect_url') ?? '',
			$row->int('num_topics'),
			$row->int('num_posts'),
			$row->nullableInt('last_post'),
			$row->nullableInt('last_post_id'),
			$row->nullableString('last_poster')
		), $rows);
	}

	public function unansweredTopics(int $groupId, ?int $posterId): array {
		return $this->topicList('', array(), $groupId, $posterId, 'WHERE '.self::READABLE.' AND t.num_replies=0 AND t.moved_to IS NULL', array(), 't.last_post DESC');
	}

	public function forums(int $groupId): array {
		$rows = $this->db->select('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.redirect_url FROM '.$this->db->table('categories').' AS c'.
			' INNER JOIN '.$this->db->table('forums').' AS f ON c.id=f.cat_id'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)'.
			' WHERE '.self::READABLE.' AND f.redirect_url IS NULL ORDER BY c.disp_position, c.id, f.disp_position', $groupId);

		return array_map(static fn (Row $row): SearchableForum => new SearchableForum($row->int('cid'), $row->string('cat_name'), $row->int('fid'), $row->string('forum_name')), $rows);
	}

	/**
	 * Topics joined to $joins, then to their forum, the forum permissions of
	 * $groupId unless it is null, and who posted when $posterId is given.
	 *
	 * @param list<int> $joinParameters
	 * @param list<int> $whereParameters
	 * @return list<ResultTopic>
	 */
	private function topicList(string $joins, array $joinParameters, ?int $groupId, ?int $posterId, string $where, array $whereParameters, string $order): array {
		$sql = 'SELECT '.self::TOPIC_COLUMNS.($posterId !== null ? ', p.poster_id AS has_posted' : '').' FROM '.$this->db->table('topics').' AS t'.$joins.
			' INNER JOIN '.$this->db->table('forums').' AS f ON f.id=t.forum_id';
		$bound = $joinParameters;

		if ($groupId !== null)
		{
			$sql .= ' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=f.id AND fp.group_id=?)';
			$bound[] = $groupId;
		}

		if ($posterId !== null)
		{
			$sql .= ' LEFT JOIN '.$this->db->table('posts').' AS p ON (p.poster_id=? AND p.topic_id=t.id)';
			$bound[] = $posterId;
		}

		$sql .= ' '.$where.($posterId !== null ? ' GROUP BY '.self::TOPIC_GROUP.', p.poster_id' : '').' ORDER BY '.$order;

		return $this->topicsOf($this->db->select($sql, ...$bound, ...$whereParameters), $posterId);
	}

	/**
	 * @param list<Row> $rows
	 * @return list<ResultPost>
	 */
	private function postsOf(array $rows): array {
		return array_map(static fn (Row $row): ResultPost => new ResultPost(
			$row->int('pid'),
			$row->string('pposter'),
			$row->int('poster_id'),
			$row->int('pposted'),
			$row->nullableString('message') ?? '',
			$row->int('hide_smilies') === 1,
			$row->int('tid'),
			$row->string('poster'),
			$row->string('subject'),
			$row->int('first_post_id'),
			$row->int('posted'),
			$row->nullableInt('last_post') ?? 0,
			$row->nullableInt('last_post_id') ?? 0,
			$row->nullableString('last_poster') ?? '',
			$row->int('num_replies'),
			$row->int('forum_id'),
			$row->string('forum_name')
		), $rows);
	}

	/**
	 * @param list<Row> $rows
	 * @return list<ResultTopic>
	 */
	private function topicsOf(array $rows, ?int $posterId): array {
		return array_map(static fn (Row $row): ResultTopic => new ResultTopic(
			$row->int('tid'),
			$row->string('poster'),
			$row->string('subject'),
			$row->int('first_post_id'),
			$row->int('posted'),
			$row->nullableInt('last_post') ?? 0,
			$row->nullableInt('last_post_id') ?? 0,
			$row->nullableString('last_poster') ?? '',
			$row->int('num_replies'),
			$row->int('closed') === 1,
			$row->int('sticky') === 1,
			$row->int('forum_id'),
			$row->string('forum_name'),
			$posterId !== null && $row->nullableInt('has_posted') === $posterId
		), $rows);
	}

	private static function direction(string $sortDir): string {
		return $sortDir === 'ASC' ? 'ASC' : 'DESC';
	}
}
