<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Index;

use PunBB\Module\Index\Event\IndexRequested;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs in_start.
 */
final class IndexRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(IndexRequested $event): void {
		$this->scope->observe('in_start', $event);
	}
}
