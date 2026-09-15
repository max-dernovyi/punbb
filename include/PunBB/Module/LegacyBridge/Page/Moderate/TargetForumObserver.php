<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\TargetForumRendering;

/**
 * Renders the points around a forum's option in the list of forums topics
 * move to, with the forum as $cur_forum, as moderate.php looped over them, and
 * after it the category of the group the list is in as $forum_page['cur_category'].
 */
final class TargetForumObserver {
	public const POINTS = array(
		TargetForumRendering::START	=> 'mr_move_topics_forum_loop_start',
		TargetForumRendering::END	=> 'mr_move_topics_forum_loop_end',
	);

	public function __construct(private readonly PageScope $scope, private readonly ModerationRows $rows) {}

	public function observe(TargetForumRendering $event): void {
		$GLOBALS['cur_forum'] = $this->rows->target($event->forum());

		if ($event->position() === TargetForumRendering::END)
			ForumPage::set('cur_category', $event->forum()->categoryId());

		$event->append($this->scope->renderObserved(self::POINTS[$event->position()], $event));
	}
}
