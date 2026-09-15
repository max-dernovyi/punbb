<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Api\Data;

/**
 * Where a member's last visit is moved to: everything posted before it counts as read.
 */
interface LastVisitInterface {
	public function userId(): int;

	public function at(): int;
}
