<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\Site\Removal\UserRemovalInterface;

/**
 * delete_user() of include/functions.php, with the extension code attached to it.
 */
final class LegacyUserRemoval implements UserRemovalInterface {
	public function remove(int $userId, bool $withPosts): void {
		\delete_user($userId, $withPosts);
	}
}
