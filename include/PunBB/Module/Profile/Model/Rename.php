<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Model;

use PunBB\Module\Profile\Api\Data\RenameInterface;

final readonly class Rename implements RenameInterface {
	public function __construct(private int $userId, private string $oldName, private string $newName) {}

	public function userId(): int {
		return $this->userId;
	}

	public function oldName(): string {
		return $this->oldName;
	}

	public function newName(): string {
		return $this->newName;
	}
}
