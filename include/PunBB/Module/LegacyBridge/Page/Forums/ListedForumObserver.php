<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Forums;

use PunBB\Module\Forums\Event\ListedForumRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the points of a listed forum's fieldset with the forum's row as
 * $cur_forum, its category as $cur_category and the form's counts in
 * $forum_page, read back for the fields that follow.
 */
final class ListedForumObserver {
	public const POINTS = array(
		ListedForumRendering::PRE_EDIT_CUR_FORUM_FIELDSET		=> 'afo_pre_edit_cur_forum_fieldset',
		ListedForumRendering::PRE_EDIT_CUR_FORUM_NAME			=> 'afo_pre_edit_cur_forum_name',
		ListedForumRendering::PRE_EDIT_CUR_FORUM_POSITION		=> 'afo_pre_edit_cur_forum_position',
		ListedForumRendering::PRE_EDIT_CUR_FORUM_FIELDSET_END	=> 'afo_pre_edit_cur_forum_fieldset_end',
		ListedForumRendering::EDIT_CUR_FORUM_FIELDSET_END		=> 'afo_edit_cur_forum_fieldset_end',
	);

	public function __construct(private readonly PageScope $scope, private readonly ForumsRows $rows) {}

	public function observe(ListedForumRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['cur_forum'] = $this->rows->listed($event->forum());
		$GLOBALS['cur_category'] = $event->forum()->categoryId();

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
