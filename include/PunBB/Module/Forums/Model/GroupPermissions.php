<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Model;

use PunBB\Module\Forums\Api\Data\GroupPermissionsInterface;

final readonly class GroupPermissions implements GroupPermissionsInterface {
	public function __construct(
		private int $groupId,
		private string $groupTitle,
		private bool $readsBoard,
		private bool $postsReplies,
		private bool $postsTopics,
		private ?bool $readForum,
		private ?bool $postReplies,
		private ?bool $postTopics
	) {}

	public function groupId(): int {
		return $this->groupId;
	}

	public function groupTitle(): string {
		return $this->groupTitle;
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

	public function readForum(): ?bool {
		return $this->readForum;
	}

	public function postReplies(): ?bool {
		return $this->postReplies;
	}

	public function postTopics(): ?bool {
		return $this->postTopics;
	}
}
