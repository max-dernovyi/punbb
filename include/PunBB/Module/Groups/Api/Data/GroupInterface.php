<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Api\Data;

/**
 * A user group as the groups page edits it; the id is 0 for a group not stored yet.
 */
interface GroupInterface {
	public const ADMINISTRATORS = 1;

	public const GUESTS = 2;

	public function id(): int;

	public function title(): string;

	/** The title its members show instead of their rank; null for none. */
	public function userTitle(): ?string;

	/** Whether the group allows $permission: a GroupPermission's value, the column storing it. */
	public function allows(string $permission): bool;

	/** Seconds a member waits between posts. */
	public function postFlood(): int;

	/** Seconds a member waits between searches. */
	public function searchFlood(): int;

	/** Seconds a member waits between emails. */
	public function emailFlood(): int;
}
