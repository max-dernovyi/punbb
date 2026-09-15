<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Api\Data;

/**
 * A topic posts are split off into: started by its first post, in the forum being moderated.
 */
interface NewTopicInterface {
	public function poster(): string;

	public function subject(): string;

	public function posted(): int;

	public function firstPostId(): int;

	public function forumId(): int;
}
