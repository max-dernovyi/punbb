<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Model;

use PunBB\Module\Bans\Api\Data\BanCandidateInterface;

final readonly class BanCandidate implements BanCandidateInterface {
	/** The group the board's administrators are in. */
	public const ADMIN_GROUP = 1;

	public function __construct(private int $id, private int $groupId, private string $username, private string $email, private string $registrationIp) {}

	public function id(): int {
		return $this->id;
	}

	public function groupId(): int {
		return $this->groupId;
	}

	public function isAdministrator(): bool {
		return $this->groupId === self::ADMIN_GROUP;
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
