<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Model;

use PunBB\Module\Moderate\Api\Data\TargetForumInterface;

final readonly class TargetForum implements TargetForumInterface {
	public function __construct(
		private int $categoryId,
		private string $categoryName,
		private int $forumId,
		private string $forumName
	) {}

	public function categoryId(): int {
		return $this->categoryId;
	}

	public function categoryName(): string {
		return $this->categoryName;
	}

	public function forumId(): int {
		return $this->forumId;
	}

	public function forumName(): string {
		return $this->forumName;
	}
}
