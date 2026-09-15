<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Indexing;

/**
 * The search index, which the welcome post goes into.
 */
interface PostIndexInterface {
	/** Indexes post $postId, which opens its topic: the words of $message and of $subject. */
	public function index(int $postId, string $message, string $subject): void;
}
