<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Model;

use PunBB\Module\Search\Api\Data\SearchCriteriaInterface;

final readonly class SearchCriteria implements SearchCriteriaInterface {
	/** @param list<int> $forumIds */
	public function __construct(
		private string $keywords,
		private string $author,
		private int $searchIn,
		private array $forumIds,
		private string $showAs,
		private ?int $sortBy,
		private string $sortDir
	) {}

	public function keywords(): string {
		return $this->keywords;
	}

	public function author(): string {
		return $this->author;
	}

	public function searchIn(): int {
		return $this->searchIn;
	}

	/** @return list<int> */
	public function forumIds(): array {
		return $this->forumIds;
	}

	public function showAs(): string {
		return $this->showAs;
	}

	public function sortBy(): ?int {
		return $this->sortBy;
	}

	public function sortDir(): string {
		return $this->sortDir;
	}
}
