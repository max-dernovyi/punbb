<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewtopic;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewtopic\Event\NewPostSeeking;

/**
 * Runs vt_find_new_post with the topic in $id and when the member last read it in $last_viewed.
 */
final class NewPostSeekingObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(NewPostSeeking $event): void {
		$GLOBALS['id'] = $event->topicId();
		$GLOBALS['last_viewed'] = $event->lastViewed();

		$this->scope->observe('vt_find_new_post', $event);
	}
}
