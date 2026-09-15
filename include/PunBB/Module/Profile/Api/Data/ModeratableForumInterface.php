<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Api\Data;

/**
 * A forum a member may be made a moderator of, in its category.
 */
interface ModeratableForumInterface {
	public function categoryId(): int;

	public function categoryName(): string;

	public function forumId(): int;

	public function forumName(): string;

	/** @return list<ModeratorInterface> */
	public function moderators(): array;
}
