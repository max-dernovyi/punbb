<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Api\Data;

/**
 * The span of the posts' ids.
 */
interface PostRangeInterface {
	public function lowest(): int;

	public function highest(): int;

	public function count(): int;
}
