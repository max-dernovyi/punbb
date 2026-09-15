<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Model;

use PunBB\Module\Login\Api\Data\ResettableAccountInterface;

final readonly class ResettableAccount implements ResettableAccountInterface {
	public function __construct(private int $id, private int $groupId, private string $username, private ?int $lastEmailSent) {}

	public function id(): int {
		return $this->id;
	}

	public function groupId(): int {
		return $this->groupId;
	}

	public function username(): string {
		return $this->username;
	}

	public function lastEmailSent(): ?int {
		return $this->lastEmailSent;
	}
}
