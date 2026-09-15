<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Api;

use PunBB\Module\Search\Api\Data\SearchMarkInterface;
use PunBB\Module\Search\Api\Data\StoredSearchInterface;

/**
 * Running a keyword or author search and storing what it found for its
 * results page, which only the searcher reads back.
 */
interface SearchesInterface {
	/** Records when each member, or guest by their address, last searched. */
	public function markSearched(SearchMarkInterface ...$marks): void;

	/**
	 * The posts whose indexed words match LIKE pattern $pattern.
	 *
	 * @param int $searchIn 0 in the message and the subject, 1 in the message only, -1 in the subject only
	 * @return list<int>
	 */
	public function keywordMatches(string $pattern, int $searchIn): array;

	/**
	 * The members whose username matches LIKE pattern $pattern, whatever its case.
	 *
	 * @return list<int>
	 */
	public function authorIds(string $pattern): array;

	/**
	 * The posts of members $userIds.
	 *
	 * @param list<int> $userIds
	 * @return list<int>
	 */
	public function authorPosts(array $userIds): array;

	/**
	 * Of posts $postIds, those a member of group $groupId may read: their ids,
	 * or the ids of their topics, each once.
	 *
	 * @param list<int> $postIds
	 * @param list<int>|null $forumIds the forums to search; null for every forum
	 * @return list<int>
	 */
	public function readableHits(array $postIds, int $groupId, ?array $forumIds, bool $asPosts): array;

	/** @return list<string> the ident of everyone online: a username, or a guest's address */
	public function onlineIdents(): array;

	/** Forgets the stored searches of everyone but $keptIdents; with none given, forgets nothing. */
	public function pruneCache(string ...$keptIdents): void;

	public function store(StoredSearchInterface ...$searches): void;

	/** Search $id stored for $ident; null when there is none. */
	public function stored(int $id, string $ident): ?StoredSearchInterface;
}
