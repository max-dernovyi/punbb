<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Api\Data;

/**
 * When a member's visit ended.
 */
interface LastVisitInterface {
	public function userId(): int;

	public function at(): int;
}
