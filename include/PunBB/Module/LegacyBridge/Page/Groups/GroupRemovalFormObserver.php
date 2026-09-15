<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Groups;

use PunBB\Module\Groups\Event\GroupRemovalRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the points of the form removing a group with members at their
 * positions, with $group_id and the form's counts in $forum_page, read back
 * for the fields that follow.
 */
final class GroupRemovalFormObserver {
	public const POINTS = array(
		GroupRemovalRendering::OUTPUT_START			=> 'agr_del_group_output_start',
		GroupRemovalRendering::PRE_DEL_FIELDSET		=> 'agr_del_group_pre_del_fieldset',
		GroupRemovalRendering::PRE_MOVE_TO_GROUP	=> 'agr_del_group_pre_move_to_group',
		GroupRemovalRendering::PRE_DEL_FIELDSET_END	=> 'agr_del_group_pre_del_fieldset_end',
		GroupRemovalRendering::DEL_FIELDSET_END		=> 'agr_del_group_del_fieldset_end',
		GroupRemovalRendering::END					=> 'agr_del_group_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(GroupRemovalRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['group_id'] = $event->groupId();

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
