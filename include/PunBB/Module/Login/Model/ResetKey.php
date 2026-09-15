<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Model;

use PunBB\Module\Login\Api\Data\ResetKeyInterface;

final readonly class ResetKey implements ResetKeyInterface {
	public function __construct(private int $userId, private string $key, private int $issuedAt) {}

	public function userId(): int {
		return $this->userId;
	}

	public function key(): string {
		return $this->key;
	}

	public function issuedAt(): int {
		return $this->issuedAt;
	}
}
