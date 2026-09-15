<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Model;

use PunBB\Module\Profile\Api\Data\ModeratorInterface;
use PunBB\Module\Profile\Api\Data\ModeratableForumInterface;

final readonly class ModeratableForum implements ModeratableForumInterface {
	/** @param list<ModeratorInterface> $moderators */
	public function __construct(private int $categoryId, private string $categoryName, private int $forumId, private string $forumName, private array $moderators) {}

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

	public function moderators(): array {
		return $this->moderators;
	}
}
