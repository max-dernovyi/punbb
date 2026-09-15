<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Model;

use PunBB\Module\Misc\Api\Data\RecipientInterface;

final readonly class Recipient implements RecipientInterface {
	public function __construct(private int $id, private string $username, private string $email, private int $emailSetting) {}

	public function id(): int {
		return $this->id;
	}

	public function username(): string {
		return $this->username;
	}

	public function email(): string {
		return $this->email;
	}

	public function emailSetting(): int {
		return $this->emailSetting;
	}
}
