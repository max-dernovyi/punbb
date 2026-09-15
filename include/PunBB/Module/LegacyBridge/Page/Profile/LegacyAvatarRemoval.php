<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\Profile\Avatar\AvatarRemovalInterface;

/**
 * delete_avatar() of include/functions.php, with the extension code attached to it.
 */
final class LegacyAvatarRemoval implements AvatarRemovalInterface {
	public function remove(int $userId): void {
		\delete_avatar($userId);
	}
}
