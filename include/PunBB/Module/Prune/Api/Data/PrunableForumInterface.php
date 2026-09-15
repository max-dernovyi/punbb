<?php

declare(strict_types=1);

namespace PunBB\Module\Prune\Api\Data;

/**
 * A forum topics may be pruned from, with its category.
 */
interface PrunableForumInterface {
	public function categoryId(): int;

	public function categoryName(): string;

	public function id(): int;

	public function name(): string;
}
