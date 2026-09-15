<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Api\Data;

/**
 * Who wrote posts from an address: the account's id, 1 for a guest, and the
 * name the posts carry.
 */
interface PosterInterface {
	public function id(): int;

	public function name(): string;
}
