<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Model;

use PunBB\Module\Update\Api\Data\PostRangeInterface;

final readonly class PostRange implements PostRangeInterface {
	public function __construct(private int $lowest, private int $highest, private int $count) {}

	public function lowest(): int {
		return $this->lowest;
	}

	public function highest(): int {
		return $this->highest;
	}

	public function count(): int {
		return $this->count;
	}
}
