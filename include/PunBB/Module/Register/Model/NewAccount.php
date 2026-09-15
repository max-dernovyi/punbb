<?php

declare(strict_types=1);

namespace PunBB\Module\Register\Model;

use PunBB\Module\Register\Api\Data\NewAccountInterface;

final readonly class NewAccount implements NewAccountInterface {
	public function __construct(
		private string $username,
		private int $groupId,
		private string $salt,
		private string $password,
		private string $passwordHash,
		private string $email,
		private int $emailSetting,
		private float $timezone,
		private int $dst,
		private string $language,
		private string $style,
		private int $registeredAt,
		private string $registrationIp,
		private ?string $activationKey,
		private bool $requiresVerification,
		private bool $notifiesAdmins
	) {}

	public function username(): string {
		return $this->username;
	}

	public function groupId(): int {
		return $this->groupId;
	}

	public function salt(): string {
		return $this->salt;
	}

	public function password(): string {
		return $this->password;
	}

	public function passwordHash(): string {
		return $this->passwordHash;
	}

	public function email(): string {
		return $this->email;
	}

	public function emailSetting(): int {
		return $this->emailSetting;
	}

	public function timezone(): float {
		return $this->timezone;
	}

	public function dst(): int {
		return $this->dst;
	}

	public function language(): string {
		return $this->language;
	}

	public function style(): string {
		return $this->style;
	}

	public function registeredAt(): int {
		return $this->registeredAt;
	}

	public function registrationIp(): string {
		return $this->registrationIp;
	}

	public function activationKey(): ?string {
		return $this->activationKey;
	}

	public function requiresVerification(): bool {
		return $this->requiresVerification;
	}

	public function notifiesAdmins(): bool {
		return $this->notifiesAdmins;
	}
}
