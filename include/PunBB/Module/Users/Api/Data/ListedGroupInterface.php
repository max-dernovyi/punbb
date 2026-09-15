<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Api\Data;

/**
 * A group the users can be searched by or moved to, by title.
 */
interface ListedGroupInterface {
	/** The group of the accounts that have not confirmed their address. */
	public const UNVERIFIED = 0;

	public const ADMINISTRATORS = 1;

	/** The group of the guest account, which is user 1. */
	public const GUESTS = 2;

	public function id(): int;

	public function title(): string;
}
