<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Forums;

use PunBB\Module\Forums\Event\ForumsRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the forums page's markup points outside a listed forum's fieldset at
 * their positions, with the forms' counts in $forum_page, read back for the
 * fields that follow.
 */
final class ForumsRenderingObserver {
	public const POINTS = array(
		ForumsRendering::MAIN_OUTPUT_START			=> 'afo_main_output_start',
		ForumsRendering::PRE_ADD_FORUM_FIELDSET		=> 'afo_pre_add_forum_fieldset',
		ForumsRendering::PRE_NEW_FORUM_NAME			=> 'afo_pre_new_forum_name',
		ForumsRendering::PRE_NEW_FORUM_POSITION		=> 'afo_pre_new_forum_position',
		ForumsRendering::PRE_NEW_FORUM_CAT			=> 'afo_pre_new_forum_cat',
		ForumsRendering::PRE_ADD_FORUM_FIELDSET_END	=> 'afo_pre_add_forum_fieldset_end',
		ForumsRendering::ADD_FORUM_FIELDSET_END		=> 'afo_add_forum_fieldset_end',
		ForumsRendering::END						=> 'afo_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ForumsRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
