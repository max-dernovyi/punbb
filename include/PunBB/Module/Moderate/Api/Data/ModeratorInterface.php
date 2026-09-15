<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Api\Data;

/**
 * A member a forum lists as its moderator.
 */
interface ModeratorInterface {
	public function userId(): int;

	public function username(): string;
}
