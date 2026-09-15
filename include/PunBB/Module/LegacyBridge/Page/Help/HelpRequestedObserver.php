<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Help;

use PunBB\Module\Help\Event\HelpRequested;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs he_start.
 */
final class HelpRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(HelpRequested $event): void {
		$this->scope->observe('he_start', $event);
	}
}
