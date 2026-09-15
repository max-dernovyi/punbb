<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Model;

use PunBB\Module\Forums\Api\Data\ForumPositionInterface;

final readonly class ForumPosition implements ForumPositionInterface {
	public function __construct(private int $forumId, private int $position) {}

	public function forumId(): int {
		return $this->forumId;
	}

	public function position(): int {
		return $this->position;
	}
}
