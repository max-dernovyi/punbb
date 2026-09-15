<?php

declare(strict_types=1);

namespace PunBB\Module\Categories\Model;

use PunBB\Module\Categories\Api\Data\CategoryInterface;

final readonly class Category implements CategoryInterface {
	public function __construct(private int $id, private string $name, private int $position) {}

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
