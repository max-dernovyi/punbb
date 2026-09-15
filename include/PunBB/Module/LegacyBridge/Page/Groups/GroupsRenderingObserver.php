<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Groups;

use PunBB\Module\Groups\Event\GroupsRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the groups page's markup points outside a listed group at their
 * positions, with the forms' counts in $forum_page, read back for the fields
 * that follow.
 */
final class GroupsRenderingObserver {
	public const POINTS = array(
		GroupsRendering::MAIN_OUTPUT_START				=> 'agr_main_output_start',
		GroupsRendering::PRE_ADD_GROUP_FIELDSET			=> 'agr_pre_add_group_fieldset',
		GroupsRendering::PRE_ADD_BASE_GROUP				=> 'agr_pre_add_base_group',
		GroupsRendering::PRE_ADD_GROUP_FIELDSET_END		=> 'agr_pre_add_group_fieldset_end',
		GroupsRendering::ADD_GROUP_FIELDSET_END			=> 'agr_add_group_fieldset_end',
		GroupsRendering::PRE_DEFAULT_GROUP_FIELDSET		=> 'agr_pre_default_group_fieldset',
		GroupsRendering::PRE_DEFAULT_GROUP				=> 'agr_pre_default_group',
		GroupsRendering::PRE_DEFAULT_GROUP_FIELDSET_END	=> 'agr_pre_default_group_fieldset_end',
		GroupsRendering::DEFAULT_GROUP_FIELDSET_END		=> 'agr_default_group_fieldset_end',
		GroupsRendering::END							=> 'agr_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(GroupsRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
