<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Forums;

use PunBB\Module\Forums\Event\ForumDeletionRendering;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders afo_del_forum_output_start and afo_del_forum_end around the
 * confirmation of a forum's deletion, with the forum as $forum_to_delete and
 * $forum_name.
 */
final class ForumDeletionObserver {
	public const POINTS = array(
		ForumDeletionRendering::OUTPUT_START	=> 'afo_del_forum_output_start',
		ForumDeletionRendering::END				=> 'afo_del_forum_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ForumDeletionRendering $event): void {
		$GLOBALS['forum_to_delete'] = $event->forum()->id();
		$GLOBALS['forum_name'] = $event->forum()->name();

		$event->append($this->scope->renderObserved(self::POINTS[$event->position()], $event));
	}
}
