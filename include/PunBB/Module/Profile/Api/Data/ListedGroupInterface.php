<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Api\Data;

/**
 * A group a member may be moved into.
 */
interface ListedGroupInterface {
	public function id(): int;

	public function title(): string;
}
