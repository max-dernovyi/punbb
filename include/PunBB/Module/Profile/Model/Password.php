<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Model;

use PunBB\Module\Profile\Api\Data\PasswordInterface;

final readonly class Password implements PasswordInterface {
	public function __construct(private int $userId, private string $hash) {}

	public function userId(): int {
		return $this->userId;
	}

	public function hash(): string {
		return $this->hash;
	}
}
