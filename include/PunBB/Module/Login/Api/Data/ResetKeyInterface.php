<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Api\Data;

/**
 * The key an account's password is reset with, and when it was mailed.
 */
interface ResetKeyInterface {
	public function userId(): int;

	public function key(): string;

	public function issuedAt(): int;
}
