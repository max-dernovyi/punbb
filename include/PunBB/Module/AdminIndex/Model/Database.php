<?php

declare(strict_types=1);

namespace PunBB\Module\AdminIndex\Model;

use PunBB\Module\AdminIndex\Api\Data\DatabaseInterface;

final readonly class Database implements DatabaseInterface {
	public function __construct(
		private string $name,
		private string $version,
		private ?int $rows,
		private ?int $size
	) {}

	public function name(): string {
		return $this->name;
	}

	public function version(): string {
		return $this->version;
	}

	public function rows(): ?int {
		return $this->rows;
	}

	public function size(): ?int {
		return $this->size;
	}
}
