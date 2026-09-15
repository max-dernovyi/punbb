<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extern;

use PunBB\Module\Extern\Event\ExternRequested;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs ex_start.
 */
final class ExternRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ExternRequested $event): void {
		$this->scope->observe('ex_start', $event);
	}
}
