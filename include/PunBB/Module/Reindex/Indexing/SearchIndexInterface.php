<?php

declare(strict_types=1);

namespace PunBB\Module\Reindex\Indexing;

/**
 * The words of every post and the posts each word is in, which search reads.
 * Posting, editing, deleting and moderating write to it too, and its words
 * carry points of their own, so it moves with the search page: the module
 * declares it, the bootstrap's side wires it.
 */
interface SearchIndexInterface {
	/** Empties the index, words and matches, and starts the words' ids over. */
	public function clear(): void;

	/** Indexes post $postId: the words of $message, and of $subject when the post opens its topic. */
	public function index(int $postId, string $message, ?string $subject): void;
}
