<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Model\ProfileUser;

/**
 * The member whose profile is shown, where profile.php kept them for
 * extension code: their row with every column in $user, their id in $id,
 * and whether the visitor is them in $forum_page['own_profile'].
 */
final class ProfileState {
	public static function publish(ProfileUserInterface $user): void {
		$GLOBALS['id'] = $user->id();
		$GLOBALS['user'] = $user instanceof ProfileUser ? $user->columns() : array('id' => $user->id(), 'username' => $user->username());

		$visitor = is_array($GLOBALS['forum_user'] ?? null) ? $GLOBALS['forum_user'] : array();
		ForumPage::set('own_profile', (int) Markers::markup($visitor['id'] ?? 0) === $user->id());
	}
}
