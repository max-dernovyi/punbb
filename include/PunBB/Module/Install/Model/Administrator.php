<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Model;

use PunBB\Module\Install\Api\Data\AdministratorInterface;

final readonly class Administrator implements AdministratorInterface {
	public function __construct(
		private string $username,
		private string $passwordHash,
		private string $salt,
		private string $email,
		private string $language,
		private int $registered
	) {}

	public function username(): string {
		return $this->username;
	}

	public function passwordHash(): string {
		return $this->passwordHash;
	}

	public function salt(): string {
		return $this->salt;
	}

	public function email(): string {
		return $this->email;
	}

	public function language(): string {
		return $this->language;
	}

	public function registered(): int {
		return $this->registered;
	}
}
