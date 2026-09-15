<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Model;

use PunBB\Module\Forums\Api\Data\CategoryInterface;

final readonly class Category implements CategoryInterface {
	public function __construct(private int $id, private string $name) {}

	public function id(): int {
		return $this->id;
	}

	public function name(): string {
		return $this->name;
	}
}
