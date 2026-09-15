<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewtopic;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewtopic\Event\TopicViewRequested;

/**
 * Runs vt_start.
 */
final class TopicViewRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(TopicViewRequested $event): void {
		$this->scope->observe('vt_start', $event);
	}
}
