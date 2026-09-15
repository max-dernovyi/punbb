<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewforum;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewforum\Event\ForumViewRequested;

/**
 * Runs vf_start.
 */
final class ForumViewRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ForumViewRequested $event): void {
		$this->scope->observe('vf_start', $event);
	}
}
