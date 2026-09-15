<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Model;

use PunBB\Module\Bans\Api\Data\BanInterface;

final readonly class Ban implements BanInterface {
	public function __construct(
		private int $id,
		private ?string $username,
		private ?string $ip,
		private ?string $email,
		private ?string $message,
		private ?int $expire,
		private int $creatorId,
		private ?string $creatorName = null
	) {}

	public function id(): int {
		return $this->id;
	}

	public function username(): ?string {
		return $this->username;
	}

	public function ip(): ?string {
		return $this->ip;
	}

	public function email(): ?string {
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

	public function creatorName(): ?string {
		return $this->creatorName;
	}
}
