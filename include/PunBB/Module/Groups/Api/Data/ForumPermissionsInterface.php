<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Api\Data;

/**
 * What a group may do in one forum, where the forum stores it.
 */
interface ForumPermissionsInterface {
	public function forumId(): int;

	public function readForum(): bool;

	public function postReplies(): bool;

	public function postTopics(): bool;
}
