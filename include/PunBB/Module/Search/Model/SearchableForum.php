<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Model;

use PunBB\Module\Search\Api\Data\SearchableForumInterface;

final readonly class SearchableForum implements SearchableForumInterface {
	public function __construct(
		private int $categoryId,
		private string $categoryName,
		private int $id,
		private string $name
	) {}

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
}
