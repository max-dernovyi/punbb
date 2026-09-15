<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewforum;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewforum\Event\ForumViewEnding;

/**
 * Renders vf_end, and leaves the forum in $forum_id, where the jump list below the page preselects it.
 */
final class ForumViewEndingObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ForumViewEnding $event): void {
		$event->append($this->scope->renderObserved('vf_end', $event));

		$GLOBALS['forum_id'] = $event->forum()->id();
	}
}
