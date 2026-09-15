<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Api\Data;

/**
 * What a group may do in one forum.
 */
interface ForumPermissionsInterface {
	public function groupId(): int;

	public function readForum(): bool;

	public function postReplies(): bool;

	public function postTopics(): bool;
}
