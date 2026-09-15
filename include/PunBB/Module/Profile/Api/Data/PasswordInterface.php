<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Api\Data;

/**
 * A member's new password, as the board stores it.
 */
interface PasswordInterface {
	public function userId(): int;

	public function hash(): string;
}
