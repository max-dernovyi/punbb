<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\Site\Moderation\ModeratorListsInterface;

/**
 * clean_forum_moderators() of include/functions.php, with the extension code attached to it.
 */
final class LegacyModeratorLists implements ModeratorListsInterface {
	public function clean(): void {
		\clean_forum_moderators();
	}
}
