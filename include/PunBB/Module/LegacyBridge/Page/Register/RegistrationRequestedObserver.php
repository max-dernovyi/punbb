<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Register;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Register\Event\RegistrationRequested;

/**
 * Runs rg_start.
 */
final class RegistrationRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(RegistrationRequested $event): void {
		$this->scope->observe('rg_start', $event);
	}
}
