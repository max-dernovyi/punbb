<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Model;

use PunBB\Module\Forums\Api\Data\GroupDefaultsInterface;

final readonly class GroupDefaults implements GroupDefaultsInterface {
	public function __construct(private int $groupId, private bool $readsBoard, private bool $postsReplies, private bool $postsTopics) {}

	public function groupId(): int {
		return $this->groupId;
	}

	public function readsBoard(): bool {
		return $this->readsBoard;
	}

	public function postsReplies(): bool {
		return $this->postsReplies;
	}

	public function postsTopics(): bool {
		return $this->postsTopics;
	}
}
