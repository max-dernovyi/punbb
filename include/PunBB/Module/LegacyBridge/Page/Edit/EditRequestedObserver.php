<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Edit;

use PunBB\Module\Edit\Event\EditRequested;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs ed_start.
 */
final class EditRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(EditRequested $event): void {
		$this->scope->observe('ed_start', $event);
	}
}
