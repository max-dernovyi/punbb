<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Message;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Message\Event\MaintenanceShowing;

/**
 * Runs fn_maintenance_message_start; a value its code returns lets the visitor in.
 */
final class MaintenanceShowingObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(MaintenanceShowing $event): void {
		if ($this->scope->observe('fn_maintenance_message_start', $event) !== null)
			$event->letIn();
	}
}
