<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Api\Data;

/**
 * A keyword or author search as it was stored for its results page: what it
 * found and how the results are shown.
 */
interface StoredSearchInterface {
	public function id(): int;

	/** Whose search it is: a member's username, or a guest's address. */
	public function ident(): string;

	/** @return list<int> the ids of the posts, or of the topics, it found */
	public function resultIds(): array;

	/** 1 by poster, 2 by subject, 3 by forum; anything else by when posted. */
	public function sortBy(): ?int;

	/** 'ASC' or 'DESC'. */
	public function sortDir(): string;

	/** 'posts', or 'topics'. */
	public function showAs(): string;
}
