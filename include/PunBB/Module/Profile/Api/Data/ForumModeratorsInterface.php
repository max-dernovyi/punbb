<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Api\Data;

/**
 * A forum and the members its moderators' list names.
 */
interface ForumModeratorsInterface {
	public function forumId(): int;

	/** @return list<ModeratorInterface> in the order the forum lists them */
	public function moderators(): array;
}
