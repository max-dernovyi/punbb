<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Model;

use PunBB\Module\Profile\Api\Data\EmailActivationInterface;

final readonly class EmailActivation implements EmailActivationInterface {
	public function __construct(private int $userId, private string $email, private string $key) {}

	public function userId(): int {
		return $this->userId;
	}

	public function email(): string {
		return $this->email;
	}

	public function key(): string {
		return $this->key;
	}
}
