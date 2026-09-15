<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Api\Data;

/**
 * A user group a member list can be narrowed to.
 */
interface GroupInterface {
	public function id(): int;

	public function title(): string;
}
