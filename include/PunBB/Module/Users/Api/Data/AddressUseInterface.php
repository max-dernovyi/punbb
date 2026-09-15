<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Api\Data;

/**
 * An address a user posted from: when they last did, and how many posts carry it.
 */
interface AddressUseInterface {
	/** The address; '' for posts that recorded none. */
	public function address(): string;

	public function lastUsed(): int;

	public function timesUsed(): int;
}
