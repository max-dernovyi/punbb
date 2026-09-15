<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Api\Data;

/**
 * A group in one of the groups page's lists, by title.
 */
interface ListedGroupInterface {
	public function id(): int;

	public function title(): string;
}
