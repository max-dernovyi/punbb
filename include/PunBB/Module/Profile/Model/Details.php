<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Model;

use PunBB\Module\Profile\Api\Data\DetailsInterface;

final readonly class Details implements DetailsInterface {
	/** @param array<string, string|null> $values column => value, null for NULL */
	public function __construct(private int $userId, private array $values) {}

	public function userId(): int {
		return $this->userId;
	}

	public function columns(): array {
		return array_map(strval(...), array_keys($this->values));
	}

	public function value(string $column): ?string {
		return $this->values[$column] ?? null;
	}
}
