<?php

declare(strict_types=1);

namespace PunBB\Module\Categories\Api\Data;

/**
 * A category and its place on the board index; the id is 0 for a category not stored yet.
 */
interface CategoryInterface {
	public function id(): int;

	public function name(): string;

	public function position(): int;
}
