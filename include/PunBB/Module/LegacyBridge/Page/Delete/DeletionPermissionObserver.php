<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Delete;

use PunBB\Module\Delete\Event\DeletionPermissionChecking;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs dl_pre_permission_check with the moderators as $mods_array, whether the
 * visitor moderates as $forum_page['is_admmod'], read back, and whether the
 * post opens its topic as $cur_post['is_topic'].
 */
final class DeletionPermissionObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(DeletionPermissionChecking $event): void {
		$post = $event->post();

		$mods_array = array();
		foreach ($post->moderators() as $moderator)
			$mods_array[$moderator->username()] = $moderator->userId();

		$GLOBALS['mods_array'] = $mods_array;

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['is_admmod'] = $event->moderating();
		$GLOBALS['forum_page'] = $page;

		$row = isset($GLOBALS['cur_post']) && is_array($GLOBALS['cur_post']) ? $GLOBALS['cur_post'] : array();
		$row['is_topic'] = $post->isTopic();
		$GLOBALS['cur_post'] = $row;

		if (!LegacyScope::attached('dl_pre_permission_check'))
			return;

		$this->scope->observe('dl_pre_permission_check', $event);

		$event->treatAsModerating(!empty(is_array($GLOBALS['forum_page'] ?? null) ? ($GLOBALS['forum_page']['is_admmod'] ?? false) : false));
	}
}
