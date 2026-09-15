<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Api\Data;

/**
 * A category a forum can be added to or moved to.
 */
interface CategoryInterface {
	public function id(): int;

	public function name(): string;
}
