<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Model;

use PunBB\Module\Users\Api\Data\UserBanInterface;

final readonly class UserBan implements UserBanInterface {
	public function __construct(
		private int $userId,
		private string $username,
		private string $ip,
		private string $email,
		private ?string $message,
		private ?int $expire,
		private int $creatorId
	) {}

	public function userId(): int {
		return $this->userId;
	}

	public function username(): string {
		return $this->username;
	}

	public function ip(): string {
		return $this->ip;
	}

	public function email(): string {
		return $this->email;
	}

	public function message(): ?string {
		return $this->message;
	}

	public function expire(): ?int {
		return $this->expire;
	}

	public function creatorId(): int {
		return $this->creatorId;
	}
}
