<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Delete;

use PunBB\Module\Delete\Event\DeletionRequested;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs dl_start.
 */
final class DeletionRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(DeletionRequested $event): void {
		$this->scope->observe('dl_start', $event);
	}
}
