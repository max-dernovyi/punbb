<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Model;

use PunBB\Module\Moderate\Api\Data\FirstPostInterface;

final readonly class FirstPost implements FirstPostInterface {
	public function __construct(private int $id, private string $poster, private int $posted) {}

	public function id(): int {
		return $this->id;
	}

	public function poster(): string {
		return $this->poster;
	}

	public function posted(): int {
		return $this->posted;
	}
}
