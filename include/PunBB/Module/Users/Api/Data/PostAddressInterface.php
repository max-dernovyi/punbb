<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Api\Data;

/**
 * The address a user wrote one of their posts from.
 */
interface PostAddressInterface {
	public function userId(): int;

	/** The address; '' for a post that recorded none. */
	public function address(): string;
}
