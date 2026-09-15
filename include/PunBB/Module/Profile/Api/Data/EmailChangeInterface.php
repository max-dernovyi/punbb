<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Api\Data;

/**
 * The address a member's account takes.
 */
interface EmailChangeInterface {
	public function userId(): int;

	public function email(): string;
}
