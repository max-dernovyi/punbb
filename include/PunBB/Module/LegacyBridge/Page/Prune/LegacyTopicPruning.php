<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Prune;

use PunBB\Module\Prune\Pruning\TopicPruningInterface;

/**
 * prune() of include/common_admin.php, sync_forum() and delete_orphans() of
 * include/functions.php, with the extension code attached to them.
 */
final class LegacyTopicPruning implements TopicPruningInterface {
	public function prune(int $forumId, bool $sticky, ?int $lastPostBefore): void {
		\prune($forumId, $sticky ? 1 : 0, $lastPostBefore ?? -1);
		\sync_forum($forumId);
	}

	public function removeOrphans(): void {
		\delete_orphans();
	}
}
