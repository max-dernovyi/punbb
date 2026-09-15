<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Search\Api\Data\ResultForumInterface;
use PunBB\Module\Search\Api\Data\ResultPostInterface;
use PunBB\Module\Search\Api\Data\ResultTopicInterface;
use PunBB\Module\Search\Api\Data\SearchableForumInterface;
use PunBB\Module\Search\Api\ResultsInterface;
use PunBB\Module\Search\Model\Searches;

/**
 * The query points of reading the results, with the query arrays
 * generate_cached_search_query() and generate_action_search_query() built,
 * and the search form's list of forums. Each function's start point runs
 * first, and a query array it returns answers; a query a later point changed
 * answers instead of the repository. The form's forums are left in $forums.
 */
final class ResultsPlugin {
	private const POST_COLUMNS = 'p.id AS pid, p.poster AS pposter, p.posted AS pposted, p.poster_id, p.message, p.hide_smilies, t.id AS tid, t.poster, t.subject, t.first_post_id, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.forum_id, f.forum_name';

	private const TOPIC_COLUMNS = 't.id AS tid, t.poster, t.subject, t.first_post_id, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.sticky, t.forum_id, f.forum_name';

	private const CACHED = 'sf_fn_generate_cached_search_query_';

	private const ACTION = 'sf_fn_generate_action_search_query_';

	public function __construct(private readonly PageScope $scope, private readonly SearchRows $rows) {}

	/**
	 * @param list<ResultPostInterface> $result
	 * @param list<int> $postIds
	 * @return list<ResultPostInterface>
	 */
	public function afterPosts(ResultsInterface $subject, array $result, array $postIds, ?int $sortBy, string $sortDir): array {
		$query = $this->cached(ResultsInterface::class.'::posts', $postIds, $sortBy, $sortDir, 'posts', function (string $sort) use ($postIds, $sortDir): array {
			return array(
				'SELECT'	=> self::POST_COLUMNS,
				'FROM'		=> 'posts AS p',
				'JOINS'		=> array(
					array(
						'INNER JOIN'	=> 'topics AS t',
						'ON'			=> 't.id=p.topic_id'
					),
					array(
						'INNER JOIN'	=> 'forums AS f',
						'ON'			=> 'f.id=t.forum_id'
					)
				),
				'WHERE'		=> 'p.id IN('.Searches::list($postIds).')',
				'ORDER BY'	=> $sort.' '.$sortDir
			);
		}, array('sf_fn_generate_cached_search_query_qr_get_cached_hits_as_posts'));

		return $query !== null ? $this->rows->posts(PluggedQuery::rows($query)) : $result;
	}

	/**
	 * @param list<ResultTopicInterface> $result
	 * @param list<int> $topicIds
	 * @return list<ResultTopicInterface>
	 */
	public function afterTopics(ResultsInterface $subject, array $result, array $topicIds, ?int $sortBy, string $sortDir, ?int $posterId): array {
		$points = $posterId !== null ? array('sf_fn_generate_cached_search_query_qr_get_has_posted', 'sf_fn_generate_cached_search_query_qr_get_cached_hits_as_topics') : array('sf_fn_generate_cached_search_query_qr_get_cached_hits_as_topics');

		$query = $this->cached(ResultsInterface::class.'::topics', $topicIds, $sortBy, $sortDir, 'topics', function (string $sort) use ($topicIds, $sortDir, $posterId): array {
			$query = array(
				'SELECT'	=> self::TOPIC_COLUMNS,
				'FROM'		=> 'topics AS t',
				'JOINS'		=> array(
					array(
						'INNER JOIN'	=> 'forums AS f',
						'ON'			=> 'f.id=t.forum_id'
					)
				),
				'WHERE'		=> 't.id IN('.Searches::list($topicIds).')',
				'ORDER BY'	=> $sort.' '.$sortDir
			);

			return self::withPoster($query, $posterId, 'p');
		}, $points);

		return $query !== null ? $this->rows->topics(PluggedQuery::rows($query), $posterId) : $result;
	}

	/**
	 * @param list<ResultTopicInterface> $result
	 * @return list<ResultTopicInterface>
	 */
	public function afterNewTopics(ResultsInterface $subject, array $result, int $groupId, int $since, ?int $forumId, ?int $posterId): array {
		$query = self::topicQuery($groupId, 't.last_post>'.$since.' AND t.moved_to IS NULL'.($forumId !== null ? ' AND f.id='.$forumId : ''));

		return $this->actionTopics('show_new', $forumId ?? -1, 'search_new_results', ResultsInterface::class.'::newTopics', self::withPoster($query, $posterId, 'p'), $posterId, 'new_topics_has_posted', 'new', $result);
	}

	/**
	 * @param list<ResultTopicInterface> $result
	 * @return list<ResultTopicInterface>
	 */
	public function afterRecentTopics(ResultsInterface $subject, array $result, int $groupId, int $since, ?int $posterId): array {
		$query = self::topicQuery($groupId, 't.last_post>'.$since.' AND t.moved_to IS NULL');

		return $this->actionTopics('show_recent', time() - $since, 'search_recent_results', ResultsInterface::class.'::recentTopics', self::withPoster($query, $posterId, 'p'), $posterId, 'recent_topics_has_posted', 'recent', $result);
	}

	/**
	 * @param list<ResultPostInterface> $result
	 * @return list<ResultPostInterface>
	 */
	public function afterUserPosts(ResultsInterface $subject, array $result, int $groupId, int $userId): array {
		$query = array(
			'SELECT'	=> self::POST_COLUMNS,
			'FROM'		=> 'posts AS p',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'topics AS t',
					'ON'			=> 't.id=p.topic_id'
				),
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'f.id=t.forum_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.poster_id='.$userId,
			'ORDER BY'	=> 'pposted DESC'
		);

		$changed = $this->action('show_user_posts', $userId, 'search_user_posts', 'posts', ResultsInterface::class.'::userPosts', $query, array('user_posts'));

		return $changed ? $this->rows->posts(PluggedQuery::rows($query)) : $result;
	}

	/**
	 * @param list<ResultTopicInterface> $result
	 * @return list<ResultTopicInterface>
	 */
	public function afterUserTopics(ResultsInterface $subject, array $result, int $groupId, int $userId, ?int $posterId): array {
		$query = array(
			'SELECT'	=> self::TOPIC_COLUMNS,
			'FROM'		=> 'topics AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'posts AS p',
					'ON'			=> 't.first_post_id=p.id'
				),
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'f.id=t.forum_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.poster_id='.$userId,
			'ORDER BY'	=> 't.last_post DESC'
		);

		return $this->actionTopics('show_user_topics', $userId, 'search_user_topics', ResultsInterface::class.'::userTopics', self::withPoster($query, $posterId, 'ps'), $posterId, 'user_topics_has_posted', 'user_topics', $result);
	}

	/**
	 * @param list<ResultTopicInterface> $result
	 * @return list<ResultTopicInterface>
	 */
	public function afterSubscribedTopics(ResultsInterface $subject, array $result, int $groupId, int $userId, ?int $posterId): array {
		$query = array(
			'SELECT'	=> self::TOPIC_COLUMNS,
			'FROM'		=> 'topics AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'subscriptions AS s',
					'ON'			=> '(t.id=s.topic_id AND s.user_id='.$userId.')'
				),
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'f.id=t.forum_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1)',
			'ORDER BY'	=> 't.last_post DESC'
		);

		return $this->actionTopics('show_subscriptions', $userId, 'search_subscriptions', ResultsInterface::class.'::subscribedTopics', self::withPoster($query, $posterId, 'p'), $posterId, 'subscriptions_has_posted', 'subscriptions', $result);
	}

	/**
	 * @param list<ResultForumInterface> $result
	 * @return list<ResultForumInterface>
	 */
	public function afterSubscribedForums(ResultsInterface $subject, array $result, int $groupId, int $userId): array {
		$query = array(
			'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster',
			'FROM'		=> 'categories AS c',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'c.id=f.cat_id'
				),
				array(
					'INNER JOIN'	=> 'forum_subscriptions AS fs',
					'ON'			=> '(f.id=fs.forum_id AND fs.user_id='.$userId.')'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1)',
			'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
		);

		$changed = $this->action('show_forum_subscriptions', $userId, 'search_forum_subscriptions', 'forums', ResultsInterface::class.'::subscribedForums', $query, array('forum_subscriptions'));

		return $changed ? $this->rows->forums(PluggedQuery::rows($query)) : $result;
	}

	/**
	 * @param list<ResultTopicInterface> $result
	 * @return list<ResultTopicInterface>
	 */
	public function afterUnansweredTopics(ResultsInterface $subject, array $result, int $groupId, ?int $posterId): array {
		$query = self::topicQuery($groupId, 't.num_replies=0 AND t.moved_to IS NULL');

		return $this->actionTopics('show_unanswered', null, 'search_unanswered', ResultsInterface::class.'::unansweredTopics', self::withPoster($query, $posterId, 'p'), $posterId, 'unanswered_topics_has_posted', 'unanswered', $result);
	}

	/**
	 * @param list<SearchableForumInterface> $result
	 * @return list<SearchableForumInterface>
	 */
	public function afterForums(ResultsInterface $subject, array $result, int $groupId): array {
		$query = array(
			'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.redirect_url',
			'FROM'		=> 'categories AS c',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'c.id=f.cat_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.redirect_url IS NULL',
			'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
		);

		$built = $query;
		$this->scope->plugged('se_qr_get_cats_and_forums', ResultsInterface::class.'::forums', array('query' => &$query));

		if ($query !== $built)
			$result = $this->rows->searchable(PluggedQuery::rows($query));

		$GLOBALS['forums'] = array_map(fn (SearchableForumInterface $forum): array => $this->rows->row($forum), $result);

		return $result;
	}

	/**
	 * A stored search's results query: the start point, the sort, the query
	 * points and the end point in turn.
	 *
	 * @param list<int> $ids
	 * @param \Closure(string): array<string, mixed> $build the query sorted by a column
	 * @param list<string> $points
	 * @return ?array<string, mixed> the query that answers when it is not the repository's
	 */
	private function cached(string $method, array $ids, ?int $sortBy, string $sortDir, string $showAs, \Closure $build, array $points): ?array {
		$search_results = Searches::list($ids);
		$show_as = $showAs;
		$sort_by = $sortBy;
		$sort_dir = $sortDir;
		$locals = array('search_results' => &$search_results, 'show_as' => &$show_as, 'sort_by' => &$sort_by, 'sort_dir' => &$sort_dir);

		$returned = $this->scope->plugged(self::CACHED.'start', $method, $locals);
		if (is_array($returned))
			return self::query($returned);

		$sort_by_sql = match ($sortBy) {
			1		=> $showAs === 'topics' ? 't.poster' : 'p.poster',
			2		=> 't.subject',
			3		=> 't.forum_id',
			default	=> $showAs === 'topics' ? 't.posted' : 'p.posted',
		};
		$sort = $sort_by_sql;

		if (!in_array($sortBy, array(1, 2, 3), true))
			$this->scope->plugged(self::CACHED.'qr_cached_sort_by', $method, array('sort_by_sql' => &$sort_by_sql) + $locals);

		$built = $build($sort);
		$query = $build(Markers::markup($sort_by_sql));

		foreach (array_merge($points, array(self::CACHED.'end')) as $point)
			$this->scope->plugged($point, $method, array('query' => &$query, 'sort_by_sql' => &$sort_by_sql) + $locals);

		return $query !== $built ? $query : null;
	}

	/**
	 * A quick search listing topics, through action().
	 *
	 * @param array<string, mixed> $query
	 * @param list<ResultTopicInterface> $result
	 * @return list<ResultTopicInterface>
	 */
	private function actionTopics(string $action, ?int $value, string $url, string $method, array $query, ?int $posterId, string $hasPosted, string $point, array $result): array {
		$points = $posterId !== null ? array($hasPosted, $point) : array($point);

		return $this->action($action, $value, $url, 'topics', $method, $query, $points)
			? $this->rows->topics(PluggedQuery::rows($query), $posterId)
			: $result;
	}

	/**
	 * A quick search's query: the start point, the query points and the end
	 * point in turn, over generate_action_search_query()'s parameters.
	 *
	 * @param array<string, mixed> $query
	 * @param list<string> $points each after sf_fn_generate_action_search_query_qr_get_
	 */
	private function action(string $action, ?int $value, string $url, string $showAs, string $method, array &$query, array $points): bool {
		$urls = $GLOBALS['forum_url'] ?? null;
		$search_id = $value ?? '';
		$url_type = is_array($urls) ? Markers::markup($urls[$url] ?? '') : '';
		$show_as = $showAs;
		$locals = array('action' => &$action, 'value' => &$value, 'search_id' => &$search_id, 'url_type' => &$url_type, 'show_as' => &$show_as);

		$returned = $this->scope->plugged(self::ACTION.'start', $method, $locals);
		if (is_array($returned))
		{
			$query = self::query($returned);

			return true;
		}

		$built = $query;
		foreach ($points as $point)
			$this->scope->plugged(self::ACTION.'qr_get_'.$point, $method, array('query' => &$query) + $locals);
		$this->scope->plugged(self::ACTION.'end', $method, array('query' => &$query) + $locals);

		return $query !== $built;
	}

	/**
	 * Topics joined to their forum and the group's forum permissions, most recently posted in first.
	 *
	 * @return array<string, mixed>
	 */
	private static function topicQuery(int $groupId, string $where): array {
		return array(
			'SELECT'	=> self::TOPIC_COLUMNS,
			'FROM'		=> 'topics AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'f.id=t.forum_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND '.$where,
			'ORDER BY'	=> 't.last_post DESC'
		);
	}

	/**
	 * $query also saying whether $posterId posted in each topic, through posts aliased $alias.
	 *
	 * @param array<string, mixed> $query
	 * @return array<string, mixed>
	 */
	private static function withPoster(array $query, ?int $posterId, string $alias): array {
		if ($posterId === null)
			return $query;

		$joins = is_array($query['JOINS'] ?? null) ? $query['JOINS'] : array();
		$joins[] = array(
			'LEFT JOIN'		=> 'posts AS '.$alias,
			'ON'			=> '('.$alias.'.poster_id='.$posterId.' AND '.$alias.'.topic_id=t.id)'
		);

		$query['SELECT'] = self::TOPIC_COLUMNS.', '.$alias.'.poster_id AS has_posted';
		$query['JOINS'] = $joins;
		// Must have same columns as in prev SELECT
		$query['GROUP BY'] = 't.id, t.poster, t.subject, t.first_post_id, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.sticky, t.forum_id, f.forum_name, '.$alias.'.poster_id';

		return $query;
	}

	/**
	 * @param array<mixed> $returned
	 * @return array<string, mixed>
	 */
	private static function query(array $returned): array {
		$query = array();
		foreach ($returned as $clause => $value)
			$query[(string) $clause] = $value;

		return $query;
	}
}
