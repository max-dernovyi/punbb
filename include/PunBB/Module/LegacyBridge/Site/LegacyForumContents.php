<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\Site\Removal\ForumContentsInterface;

/**
 * prune() of include/common_admin.php and delete_orphans() of
 * include/functions.php, with the extension code attached to them.
 */
final class LegacyForumContents implements ForumContentsInterface {
	public function empty(int $forumId): void {
		\prune($forumId, 1, -1);
	}

	public function removeOrphans(): void {
		\delete_orphans();
	}
}
