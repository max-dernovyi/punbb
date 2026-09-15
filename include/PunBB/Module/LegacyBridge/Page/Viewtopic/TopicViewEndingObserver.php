<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewtopic;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewtopic\Event\TopicViewEnding;

/**
 * Renders vt_end, and leaves the topic's forum in $forum_id, where the jump list below the page preselects it.
 */
final class TopicViewEndingObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(TopicViewEnding $event): void {
		$event->append($this->scope->renderObserved('vt_end', $event));

		$GLOBALS['forum_id'] = $event->topic()->forumId();
	}
}
