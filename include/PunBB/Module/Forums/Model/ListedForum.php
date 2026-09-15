<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Model;

use PunBB\Module\Forums\Api\Data\ListedForumInterface;

final readonly class ListedForum implements ListedForumInterface {
	public function __construct(private int $categoryId, private string $categoryName, private int $id, private string $name, private int $position) {}

	public function categoryId(): int {
		return $this->categoryId;
	}

	public function categoryName(): string {
		return $this->categoryName;
	}

	public function id(): int {
		return $this->id;
	}

	public function name(): string {
		return $this->name;
	}

	public function position(): int {
		return $this->position;
	}
}
