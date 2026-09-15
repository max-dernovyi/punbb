<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Indexing;

/**
 * The search index, as an edit changes it. Posting, moderating and rebuilding
 * write it too, with points of their own, so it moves with search.php: the
 * module declares it, the bootstrap's side wires it.
 */
interface EditIndexInterface {
	/** Indexes post $postId again from its edited $message, and from $subject when the post opens its topic. */
	public function update(int $postId, string $message, ?string $subject): void;
}
