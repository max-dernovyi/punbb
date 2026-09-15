<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Api\Data;

/**
 * A group that has members, as its removal lists them.
 */
interface GroupMembersInterface {
	public function title(): string;

	public function count(): int;
}
