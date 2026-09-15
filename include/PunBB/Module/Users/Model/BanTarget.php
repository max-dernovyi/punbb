<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Model;

use PunBB\Module\Users\Api\Data\BanTargetInterface;

final readonly class BanTarget implements BanTargetInterface {
	public function __construct(private int $id, private string $username, private string $email, private string $registrationIp) {}

	public function id(): int {
		return $this->id;
	}

	public function username(): string {
		return $this->username;
	}

	public function email(): string {
		return $this->email;
	}

	public function registrationIp(): string {
		return $this->registrationIp;
	}
}
