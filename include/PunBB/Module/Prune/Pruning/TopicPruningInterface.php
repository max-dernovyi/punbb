<?php

declare(strict_types=1);

namespace PunBB\Module\Prune\Pruning;

/**
 * Takes old topics off the board: their posts, subscriptions and words in the
 * search index, and the counts and last posts of their forums. Moderating and
 * deleting share the forum sync and the orphans' removal, which carry points
 * of their own, so they move with those pages: the module declares it, the
 * bootstrap's side wires it.
 */
interface TopicPruningInterface {
	/** Prunes forum $forumId of the topics last posted in before $lastPostBefore, or of every topic when it is null; sticky ones only when $sticky. */
	public function prune(int $forumId, bool $sticky, ?int $lastPostBefore): void;

	/** Removes the topics left pointing at a topic that is gone. */
	public function removeOrphans(): void;
}
