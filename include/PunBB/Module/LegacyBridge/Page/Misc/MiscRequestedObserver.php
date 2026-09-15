<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Misc;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Misc\Event\MiscRequested;

/**
 * Runs mi_start.
 */
final class MiscRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(MiscRequested $event): void {
		$this->scope->observe('mi_start', $event);
	}
}
