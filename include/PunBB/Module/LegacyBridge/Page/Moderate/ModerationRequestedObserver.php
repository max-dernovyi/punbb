<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Moderate\Event\ModerationRequested;

/**
 * Runs mr_start.
 */
final class ModerationRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ModerationRequested $event): void {
		$this->scope->observe('mr_start', $event);
	}
}
