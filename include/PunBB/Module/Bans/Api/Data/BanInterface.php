<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Api\Data;

/**
 * A ban: the username, the addresses and the email it bans, with its message
 * and when it expires. What it does not ban by is null; the id is 0 for a ban
 * not stored yet.
 */
interface BanInterface {
	public function id(): int;

	public function username(): ?string;

	/** The IP addresses and ranges, separated by spaces. */
	public function ip(): ?string;

	/** An address, or a domain every address of which is banned. */
	public function email(): ?string;

	public function message(): ?string;

	/** When the ban ends; null for a ban removed by hand. */
	public function expire(): ?int;

	/** The id of who created it. */
	public function creatorId(): int;

	/** The username of who created it; null when that account is gone. */
	public function creatorName(): ?string;
}
