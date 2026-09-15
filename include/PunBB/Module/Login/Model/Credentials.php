<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Model;

use PunBB\Module\Login\Api\Data\CredentialsInterface;

final readonly class Credentials implements CredentialsInterface {
	public function __construct(private int $userId, private int $groupId, private string $passwordHash, private string $salt) {}

	public function userId(): int {
		return $this->userId;
	}

	public function groupId(): int {
		return $this->groupId;
	}

	public function passwordHash(): string {
		return $this->passwordHash;
	}

	public function salt(): string {
		return $this->salt;
	}
}
