<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Model;

use PunBB\Module\Moderate\Api\Data\ModeratorInterface;

final readonly class Moderator implements ModeratorInterface {
	public function __construct(private int $userId, private string $username) {}

	public function userId(): int {
		return $this->userId;
	}

	public function username(): string {
		return $this->username;
	}
}
