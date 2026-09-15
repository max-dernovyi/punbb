<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Model;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Row;
use PunBB\Module\Search\Api\Data\SearchMarkInterface;
use PunBB\Module\Search\Api\Data\StoredSearchInterface;
use PunBB\Module\Search\Api\SearchesInterface;

/**
 * Searches run over the search index, users, posts, topics and forum
 * permissions tables, and stored in the search cache table.
 */
final class Searches implements SearchesInterface {
	public function __construct(private readonly Connection $db) {}

	public function markSearched(SearchMarkInterface ...$marks): void {
		foreach ($marks as $mark)
		{
			if ($mark->memberId() === null)
				$this->db->execute('UPDATE '.$this->db->table('online').' SET last_search=? WHERE ident=?', $mark->at(), $mark->address());
			else
				$this->db->execute('UPDATE '.$this->db->table('users').' SET last_search=? WHERE id=?', $mark->at(), $mark->memberId());
		}
	}

	public function keywordMatches(string $pattern, int $searchIn): array {
		$in = $searchIn === 0 ? '' : ($searchIn > 0 ? ' AND m.subject_match=0' : ' AND m.subject_match=1');

		return self::ids($this->db->select('SELECT m.post_id FROM '.$this->db->table('search_words').' AS w'.
			' INNER JOIN '.$this->db->table('search_matches').' AS m ON m.word_id=w.id WHERE w.word LIKE ?'.$in, $pattern), 'post_id');
	}

	public function authorIds(string $pattern): array {
		return self::ids($this->db->select('SELECT u.id FROM '.$this->db->table('users').' AS u WHERE u.username '.$this->db->platform()->likeIgnoringCase().' ?', $pattern), 'id');
	}

	public function authorPosts(array $userIds): array {
		if ($userIds === array())
			return array();

		return self::ids($this->db->select('SELECT p.id FROM '.$this->db->table('posts').' AS p WHERE p.poster_id IN('.self::list($userIds).')'), 'id');
	}

	public function readableHits(array $postIds, int $groupId, ?array $forumIds, bool $asPosts): array {
		if ($postIds === array())
			return array();

		$sql = 'SELECT '.($asPosts ? 'p.id' : 't.id').' FROM '.$this->db->table('posts').' AS p'.
			' INNER JOIN '.$this->db->table('topics').' AS t ON t.id=p.topic_id'.
			' LEFT JOIN '.$this->db->table('forum_perms').' AS fp ON (fp.forum_id=t.forum_id AND fp.group_id=?)'.
			' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND p.id IN('.self::list($postIds).')';

		if ($forumIds !== null)
			$sql .= ' AND t.forum_id IN('.self::list($forumIds).')';

		return self::ids($this->db->select($sql.($asPosts ? '' : ' GROUP BY t.id'), $groupId), 'id');
	}

	public function onlineIdents(): array {
		return array_map(static fn (Row $row): string => $row->string('ident'), $this->db->select('SELECT o.ident FROM '.$this->db->table('online').' AS o'));
	}

	public function pruneCache(string ...$keptIdents): void {
		if ($keptIdents === array())
			return;

		$this->db->execute('DELETE FROM '.$this->db->table('search_cache').' WHERE ident NOT IN('.implode(',', array_fill(0, count($keptIdents), '?')).')', ...$keptIdents);
	}

	public function store(StoredSearchInterface ...$searches): void {
		foreach ($searches as $search)
			$this->db->execute('INSERT INTO '.$this->db->table('search_cache').' (id, ident, search_data) VALUES(?, ?, ?)', $search->id(), $search->ident(), self::serialized($search));
	}

	public function stored(int $id, string $ident): ?StoredSearchInterface {
		$data = $this->db->selectValue('SELECT sc.search_data FROM '.$this->db->table('search_cache').' AS sc WHERE sc.id=? AND sc.ident=?', $id, $ident);

		return $data !== null ? self::unserialized($id, $ident, (string) $data) : null;
	}

	/** Search $id of $ident from what search.php stored; null for anything else. */
	public static function unserialized(int $id, string $ident, string $data): ?StoredSearch {
		// search.php stored an array; anything else is not a search
		$search = str_starts_with($data, 'a:') ? unserialize($data, array('allowed_classes' => false)) : null;
		if (!is_array($search))
			return null;

		$results = is_scalar($search['search_results'] ?? null) ? (string) $search['search_results'] : '';
		$sortBy = $search['sort_by'] ?? null;

		return new StoredSearch(
			$id,
			$ident,
			$results !== '' ? array_map(intval(...), explode(',', $results)) : array(),
			is_int($sortBy) ? $sortBy : null,
			($search['sort_dir'] ?? null) === 'ASC' ? 'ASC' : 'DESC',
			is_string($search['show_as'] ?? null) ? $search['show_as'] : 'topics'
		);
	}

	/** The search as search.php stored it, so a stored search reads back whichever wrote it. */
	public static function serialized(StoredSearchInterface $search): string {
		return serialize(array(
			'search_results'	=> implode(',', $search->resultIds()),
			'sort_by'			=> $search->sortBy(),
			'sort_dir'			=> $search->sortDir(),
			'show_as'			=> $search->showAs(),
		));
	}

	/**
	 * An id list for IN(): integers written into the statement, as a search can
	 * match more posts than a statement takes parameters.
	 *
	 * @param list<int> $ids
	 */
	public static function list(array $ids): string {
		return implode(',', array_map(intval(...), $ids));
	}

	/**
	 * @param list<Row> $rows
	 * @return list<int>
	 */
	private static function ids(array $rows, string $column): array {
		return array_map(static fn (Row $row): int => $row->int($column), $rows);
	}
}
