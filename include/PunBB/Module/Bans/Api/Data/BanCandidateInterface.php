<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Api\Data;

/**
 * A member a ban is being made for, with what the ban form is filled in from.
 */
interface BanCandidateInterface {
	public function id(): int;

	public function groupId(): int;

	/** Whether the member is in the administrators' group, which no ban may touch. */
	public function isAdministrator(): bool;

	public function username(): string;

	public function email(): string;

	public function registrationIp(): string;
}
