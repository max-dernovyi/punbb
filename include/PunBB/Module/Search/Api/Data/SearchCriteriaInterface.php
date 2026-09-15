<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Api\Data;

/**
 * What a keyword or author search asks for, as the search form sent it.
 */
interface SearchCriteriaInterface {
	public function keywords(): string;

	public function author(): string;

	/** 0 in the message and the subject, 1 in the message only, -1 in the subject only. */
	public function searchIn(): int;

	/** @return list<int> the forums to search; -1 among them for every forum */
	public function forumIds(): array;

	/** 'posts' or 'topics'. */
	public function showAs(): string;

	/** Null when the form sent none. */
	public function sortBy(): ?int;

	/** 'ASC' or 'DESC'. */
	public function sortDir(): string;
}
