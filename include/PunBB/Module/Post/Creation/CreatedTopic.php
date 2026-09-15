<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Creation;

/**
 * A topic just stored, with the post that opens it.
 */
final readonly class CreatedTopic {
	public function __construct(public int $topicId, public int $postId) {}
}
