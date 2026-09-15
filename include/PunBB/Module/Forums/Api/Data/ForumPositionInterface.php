<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Api\Data;

/**
 * A forum's place among the forums of its category.
 */
interface ForumPositionInterface {
	public function forumId(): int;

	public function position(): int;
}
