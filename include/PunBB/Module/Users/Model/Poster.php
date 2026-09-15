<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Model;

use PunBB\Module\Users\Api\Data\PosterInterface;

final readonly class Poster implements PosterInterface {
	public function __construct(private int $id, private string $name) {}

	public function id(): int {
		return $this->id;
	}

	public function name(): string {
		return $this->name;
	}
}
