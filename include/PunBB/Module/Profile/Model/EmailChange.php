<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Model;

use PunBB\Module\Profile\Api\Data\EmailChangeInterface;

final readonly class EmailChange implements EmailChangeInterface {
	public function __construct(private int $userId, private string $email) {}

	public function userId(): int {
		return $this->userId;
	}

	public function email(): string {
		return $this->email;
	}
}
