<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Indexing;

/**
 * The words the search finds posts by. Posting, editing and deleting write it
 * too, with points of their own: the module declares it, the bootstrap's side
 * wires it.
 */
interface PostIndexInterface {
	/** Forgets the words of the posts $postIds, and the words no post has any more. */
	public function strip(int ...$postIds): void;
}
