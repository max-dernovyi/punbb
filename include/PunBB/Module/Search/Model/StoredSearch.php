<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Model;

use PunBB\Module\Search\Api\Data\StoredSearchInterface;

final readonly class StoredSearch implements StoredSearchInterface {
	/** @param list<int> $resultIds */
	public function __construct(
		private int $id,
		private string $ident,
		private array $resultIds,
		private ?int $sortBy,
		private string $sortDir,
		private string $showAs
	) {}

	public function id(): int {
		return $this->id;
	}

	public function ident(): string {
		return $this->ident;
	}

	/** @return list<int> */
	public function resultIds(): array {
		return $this->resultIds;
	}

	public function sortBy(): ?int {
		return $this->sortBy;
	}

	public function sortDir(): string {
		return $this->sortDir;
	}

	public function showAs(): string {
		return $this->showAs;
	}
}
