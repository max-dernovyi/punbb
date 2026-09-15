<?php

declare(strict_types=1);

namespace PunBB\Module\Reindex\Api;

use PunBB\Module\Reindex\Api\Data\IndexablePostInterface;

/**
 * The posts a rebuild of the search index walks, in the order of their ids.
 */
interface IndexablePostsInterface {
	/** The lowest post id; null when there is no post. */
	public function firstId(): ?int;

	/** @return list<IndexablePostInterface> up to $limit posts from id $startAt on, with their topics */
	public function batch(int $startAt, int $limit): array;

	/** The lowest post id above $postId; null when there is none. */
	public function nextId(int $postId): ?int;
}
