<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Model;

use PunBB\Module\Users\Api\Data\PostAddressInterface;

final readonly class PostAddress implements PostAddressInterface {
	public function __construct(private int $userId, private string $address) {}

	public function userId(): int {
		return $this->userId;
	}

	public function address(): string {
		return $this->address;
	}
}
