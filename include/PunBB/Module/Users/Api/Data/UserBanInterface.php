<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Api\Data;

/**
 * A ban on one user, by their username, an address and their email.
 */
interface UserBanInterface {
	/** The user banned. */
	public function userId(): int;

	public function username(): string;

	/** The address the user posted from last, or registered from. */
	public function ip(): string;

	public function email(): string;

	/** What the banned user is told; null for nothing. */
	public function message(): ?string;

	/** When the ban ends; null for a ban removed by hand. */
	public function expire(): ?int;

	/** The id of who created it. */
	public function creatorId(): int;
}
