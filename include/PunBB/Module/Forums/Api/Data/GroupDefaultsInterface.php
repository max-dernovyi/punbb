<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Api\Data;

/**
 * What a group may do on the whole board, which a forum's permissions start from.
 */
interface GroupDefaultsInterface {
	public const ADMINISTRATORS = 1;

	public function groupId(): int;

	public function readsBoard(): bool;

	public function postsReplies(): bool;

	public function postsTopics(): bool;
}
