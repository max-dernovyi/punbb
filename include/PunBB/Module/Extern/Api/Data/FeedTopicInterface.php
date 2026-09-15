<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Api\Data;

/**
 * The topic a feed of posts follows.
 */
interface FeedTopicInterface {
	public function id(): int;

	public function subject(): string;

	public function firstPostId(): int;
}
