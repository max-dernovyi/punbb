<?php

declare(strict_types=1);

namespace PunBB\Module\Prune\Api;

use PunBB\Module\Prune\Api\Data\PrunableForumInterface;

/**
 * The forums topics are pruned from, and how many topics a prune would take.
 */
interface PrunableTopicsInterface {
	/** @return list<PrunableForumInterface> the forums of this board, by category, in the order the board lists them */
	public function forums(): array;

	/** @return list<int> the id of every forum, those on other sites included */
	public function forumIds(): array;

	/** Forum $forumId's name; null when there is none. */
	public function forumName(int $forumId): ?string;

	/**
	 * How many topics of forum $forumId, or of every forum when it is null,
	 * were last posted in before $lastPostBefore; sticky ones only when $sticky.
	 * A topic moved elsewhere is not counted.
	 */
	public function count(?int $forumId, int $lastPostBefore, bool $sticky): int;
}
