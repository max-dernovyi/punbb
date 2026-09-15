<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\ModeratorChecking;

/**
 * Runs mr_pre_permission_check with the forum's moderators as $mods_array,
 * read back: the visitor moderates as the page script decided it, from the
 * moderators and $forum_user as extension code left them.
 */
final class ModeratorCheckingObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ModeratorChecking $event): void {
		$mods_array = array();
		foreach ($event->forum()->moderators() as $moderator)
			$mods_array[$moderator->username()] = $moderator->userId();

		$GLOBALS['mods_array'] = $mods_array;

		if (!LegacyScope::attached('mr_pre_permission_check'))
			return;

		$this->scope->observe('mr_pre_permission_check', $event);

		$user = is_array($GLOBALS['forum_user'] ?? null) ? $GLOBALS['forum_user'] : array();
		$moderators = is_array($GLOBALS['mods_array'] ?? null) ? $GLOBALS['mods_array'] : array();

		$event->treatAsModerating((int) Markers::markup($user['g_id'] ?? 0) === (int) Markers::markup(\FORUM_ADMIN)
			|| (Markers::markup($user['g_moderator'] ?? '') === '1' && array_key_exists(Markers::markup($user['username'] ?? ''), $moderators)));
	}
}
