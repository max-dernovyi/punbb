<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Model;

use PunBB\Module\Forums\Api\Data\ForumInterface;

final readonly class Forum implements ForumInterface {
	public function __construct(
		private int $id,
		private string $name,
		private ?string $description = null,
		private ?string $redirectUrl = null,
		private int $sortBy = 0,
		private int $categoryId = 0,
		private int $position = 0,
		private int $topicCount = 0
	) {}

	public function id(): int {
		return $this->id;
	}

	public function name(): string {
		return $this->name;
	}

	public function description(): ?string {
		return $this->description;
	}

	public function redirectUrl(): ?string {
		return $this->redirectUrl;
	}

	public function sortBy(): int {
		return $this->sortBy;
	}

	public function categoryId(): int {
		return $this->categoryId;
	}

	public function position(): int {
		return $this->position;
	}

	public function topicCount(): int {
		return $this->topicCount;
	}
}
