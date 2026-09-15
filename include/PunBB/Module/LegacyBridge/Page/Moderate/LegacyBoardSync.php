<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\Moderate\Sync\BoardSyncInterface;

/**
 * sync_topic() and sync_forum() of include/functions.php, with the extension code attached to them.
 */
final class LegacyBoardSync implements BoardSyncInterface {
	public function topic(int $topicId): void {
		\sync_topic($topicId);
	}

	public function forum(int $forumId): void {
		\sync_forum($forumId);
	}
}
