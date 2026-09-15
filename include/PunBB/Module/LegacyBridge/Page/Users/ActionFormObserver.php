<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Users\Event\ActionFormRendering;

/**
 * Renders the points at the start and the end of the forms asked for the
 * users selected, with them as $users and the form's counts in $forum_page,
 * read back for the fields that follow.
 */
final class ActionFormObserver {
	public const POINTS = array(
		ActionFormRendering::DELETE			=> array(ActionFormRendering::OUTPUT_START => 'aus_delete_users_output_start', ActionFormRendering::END => 'aus_delete_users_end'),
		ActionFormRendering::BAN			=> array(ActionFormRendering::OUTPUT_START => 'aus_ban_users_output_start', ActionFormRendering::END => 'aus_ban_users_end'),
		ActionFormRendering::CHANGE_GROUP	=> array(ActionFormRendering::OUTPUT_START => 'aus_change_group_output_start', ActionFormRendering::END => 'aus_change_group_end'),
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ActionFormRendering $event): void {
		$point = self::POINTS[$event->form()][$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['users'] = $event->ids();

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
