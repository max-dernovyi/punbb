<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Api\Data;

/**
 * A moderator of a forum, as the forum lists them.
 */
interface ModeratorInterface {
	public function userId(): int;

	public function username(): string;
}
