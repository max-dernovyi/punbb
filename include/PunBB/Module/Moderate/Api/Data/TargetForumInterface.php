<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Api\Data;

/**
 * A forum topics can be moved to, with its category.
 */
interface TargetForumInterface {
	public function categoryId(): int;

	public function categoryName(): string;

	public function forumId(): int;

	public function forumName(): string;
}
