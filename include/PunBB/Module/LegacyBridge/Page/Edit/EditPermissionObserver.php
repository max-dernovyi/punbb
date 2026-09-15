<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Edit;

use PunBB\Module\Edit\Event\EditPermissionChecking;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs ed_pre_permission_check with the moderators as $mods_array and whether
 * the visitor moderates as $forum_page['is_admmod'], read back.
 */
final class EditPermissionObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(EditPermissionChecking $event): void {
		$mods_array = array();
		foreach ($event->post()->moderators() as $moderator)
			$mods_array[$moderator->username()] = $moderator->userId();

		$GLOBALS['mods_array'] = $mods_array;
		ForumPage::set('is_admmod', $event->moderating());

		if (!LegacyScope::attached('ed_pre_permission_check'))
			return;

		$this->scope->observe('ed_pre_permission_check', $event);

		$event->treatAsModerating(!empty(ForumPage::get('is_admmod')));
	}
}
