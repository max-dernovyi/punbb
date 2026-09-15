<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PluggedQuery;
use PunBB\Module\Search\Api\Data\SearchMarkInterface;
use PunBB\Module\Search\Api\Data\StoredSearchInterface;
use PunBB\Module\Search\Api\SearchesInterface;
use PunBB\Module\Search\Model\Searches;

/**
 * The query points of running a search, with the query arrays
 * create_search_cache() and generate_cached_search_query() built. A query a
 * point changed answers instead; a statement a point changed runs instead,
 * and the repository is handed nothing to store.
 */
final class SearchesPlugin {
	public function __construct(private readonly PluggedQuery $queries) {}

	/** @return list<SearchMarkInterface>|null */
	public function beforeMarkSearched(SearchesInterface $subject, SearchMarkInterface ...$marks): ?array {
		$kept = array();
		foreach ($marks as $mark)
		{
			$query = $mark->memberId() === null
				? array('UPDATE' => 'online', 'SET' => 'last_search='.$mark->at(), 'WHERE' => 'ident=\''.self::escape($mark->address()).'\'')
				: array('UPDATE' => 'users', 'SET' => 'last_search='.$mark->at(), 'WHERE' => 'id='.$mark->memberId());

			if ($this->queries->changed('sf_fn_create_search_cache_qr_update_last_search_time', SearchesInterface::class.'::markSearched', $query))
				PluggedQuery::run($query);
			else
				$kept[] = $mark;
		}

		return count($kept) !== count($marks) ? $kept : null;
	}

	/**
	 * @param list<int> $result
	 * @return list<int>
	 */
	public function afterKeywordMatches(SearchesInterface $subject, array $result, string $pattern, int $searchIn): array {
		$query = array(
			'SELECT'	=> 'm.post_id',
			'FROM'		=> 'search_words AS w',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'search_matches AS m',
					'ON'			=> 'm.word_id=w.id'
				)
			),
			'WHERE'		=> 'w.word LIKE \''.self::escape($pattern).'\''
		);

		if ($searchIn !== 0)
			$query['WHERE'] .= ($searchIn > 0 ? ' AND m.subject_match=0' : ' AND m.subject_match=1');

		$cur_word = str_replace('%', '*', $pattern);
		$search_in = $searchIn;

		return $this->queries->changed('sf_fn_create_search_cache_qr_get_keyword_hits', SearchesInterface::class.'::keywordMatches', $query, array('cur_word' => &$cur_word, 'search_in' => &$search_in))
			? self::firstColumn($query)
			: $result;
	}

	/**
	 * @param list<int> $result
	 * @return list<int>
	 */
	public function afterAuthorIds(SearchesInterface $subject, array $result, string $pattern): array {
		$query = array(
			'SELECT'	=> 'u.id',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.username '.(($GLOBALS['db_type'] ?? null) === 'pgsql' ? 'ILIKE' : 'LIKE').' \''.self::escape($pattern).'\''
		);

		$author = str_replace('%', '*', $pattern);

		return $this->queries->changed('sf_fn_create_search_cache_qr_get_author', SearchesInterface::class.'::authorIds', $query, array('author' => &$author))
			? self::firstColumn($query)
			: $result;
	}

	/**
	 * @param list<int> $result
	 * @param list<int> $userIds
	 * @return list<int>
	 */
	public function afterAuthorPosts(SearchesInterface $subject, array $result, array $userIds): array {
		if ($userIds === array())
			return $result;

		$query = array(
			'SELECT'	=> 'p.id',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.poster_id IN('.Searches::list($userIds).')'
		);

		$user_ids = $userIds;

		return $this->queries->changed('sf_fn_create_search_cache_qr_get_author_hits', SearchesInterface::class.'::authorPosts', $query, array('user_ids' => &$user_ids))
			? self::firstColumn($query)
			: $result;
	}

	/**
	 * @param list<int> $result
	 * @param list<int> $postIds
	 * @param ?list<int> $forumIds
	 * @return list<int>
	 */
	public function afterReadableHits(SearchesInterface $subject, array $result, array $postIds, int $groupId, ?array $forumIds, bool $asPosts): array {
		if ($postIds === array())
			return $result;

		$query = array(
			'SELECT'	=> 't.id',
			'FROM'		=> 'posts AS p',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'topics AS t',
					'ON'			=> 't.id=p.topic_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=t.forum_id AND fp.group_id='.$groupId.')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.id IN('.Searches::list($postIds).')',
			'GROUP BY'	=> 't.id'
		);

		if ($forumIds !== null)
			$query['WHERE'] .= ' AND t.forum_id IN('.Searches::list($forumIds).')';

		if ($asPosts)
		{
			$query['SELECT'] = 'p.id';
			unset($query['GROUP BY']);
		}

		$search_ids = $postIds;
		$forum = $forumIds ?? array(-1);
		$show_as = $asPosts ? 'posts' : 'topics';

		return $this->queries->changed('sf_fn_create_search_cache_qr_get_hits', SearchesInterface::class.'::readableHits', $query, array('search_ids' => &$search_ids, 'forum' => &$forum, 'show_as' => &$show_as))
			? self::firstColumn($query)
			: $result;
	}

	/**
	 * @param list<string> $result
	 * @return list<string>
	 */
	public function afterOnlineIdents(SearchesInterface $subject, array $result): array {
		$query = array(
			'SELECT'	=> 'o.ident',
			'FROM'		=> 'online AS o'
		);

		if (!$this->queries->changed('sf_fn_create_search_cache_qr_get_online_idents', SearchesInterface::class.'::onlineIdents', $query))
			return $result;

		$idents = array();
		foreach (PluggedQuery::rows($query) as $row)
			$idents[] = Markers::markup(array_values($row)[0] ?? '');

		return $idents;
	}

	/** @return list<string>|null */
	public function beforePruneCache(SearchesInterface $subject, string ...$keptIdents): ?array {
		if ($keptIdents === array())
			return null;

		$online_idents = array_map(static fn (string $ident): string => '\''.self::escape($ident).'\'', $keptIdents);
		$query = array(
			'DELETE'	=> 'search_cache',
			'WHERE'		=> 'ident NOT IN('.implode(',', $online_idents).')'
		);

		if (!$this->queries->changed('sf_fn_create_search_cache_qr_delete_old_cached_searches', SearchesInterface::class.'::pruneCache', $query, array('online_idents' => &$online_idents)))
			return null;

		PluggedQuery::run($query);

		return array();
	}

	/** @return list<StoredSearchInterface>|null */
	public function beforeStore(SearchesInterface $subject, StoredSearchInterface ...$searches): ?array {
		$kept = array();
		foreach ($searches as $search)
		{
			$search_id = $search->id();
			$ident = $search->ident();
			$search_data = Searches::serialized($search);
			$query = array(
				'INSERT'	=> 'id, ident, search_data',
				'INTO'		=> 'search_cache',
				'VALUES'	=> $search_id.', \''.self::escape($ident).'\', \''.self::escape($search_data).'\''
			);

			if ($this->queries->changed('sf_fn_create_search_cache_qr_cache_search', SearchesInterface::class.'::store', $query, array('search_id' => &$search_id, 'ident' => &$ident, 'search_data' => &$search_data)))
				PluggedQuery::run($query);
			else
				$kept[] = $search;
		}

		return count($kept) !== count($searches) ? $kept : null;
	}

	public function afterStored(SearchesInterface $subject, ?StoredSearchInterface $result, int $id, string $ident): ?StoredSearchInterface {
		$query = array(
			'SELECT'	=> 'sc.search_data',
			'FROM'		=> 'search_cache AS sc',
			'WHERE'		=> 'sc.id='.$id.' AND sc.ident=\''.self::escape($ident).'\''
		);

		$search_id = $id;

		if (!$this->queries->changed('sf_fn_generate_cached_search_query_qr_get_cached_search_data', SearchesInterface::class.'::stored', $query, array('search_id' => &$search_id, 'ident' => &$ident)))
			return $result;

		$data = PluggedQuery::rows($query)[0]['search_data'] ?? null;

		return is_string($data) ? Searches::unserialized($id, $ident, $data) : null;
	}

	/**
	 * The first column of every row of $query, as the search read it with fetch_row().
	 *
	 * @param array<string, mixed> $query
	 * @return list<int>
	 */
	private static function firstColumn(array $query): array {
		$ids = array();
		foreach (PluggedQuery::rows($query) as $row)
			$ids[] = (int) Markers::markup(array_values($row)[0] ?? 0);

		return $ids;
	}

	private static function escape(string $value): string {
		return Markers::markup(LegacyConnection::legacy()->escape($value));
	}
}
