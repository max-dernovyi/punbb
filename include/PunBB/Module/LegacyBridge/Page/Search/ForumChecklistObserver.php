<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\ForumChecklistRendering;

/**
 * Renders se_forum_loop_start and se_forum_loop_end with the forum as
 * $cur_forum and the form's counts in $forum_page, which are read back.
 */
final class ForumChecklistObserver {
	public const POINTS = array(
		ForumChecklistRendering::START	=> 'se_forum_loop_start',
		ForumChecklistRendering::END	=> 'se_forum_loop_end',
	);

	public function __construct(private readonly PageScope $scope, private readonly SearchRows $rows) {}

	public function observe(ForumChecklistRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['cur_forum'] = $this->rows->row($event->forum());
		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
