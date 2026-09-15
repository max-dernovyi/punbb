<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Login;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Login\Event\LoginRequested;

/**
 * Runs li_start.
 */
final class LoginRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(LoginRequested $event): void {
		$this->scope->observe('li_start', $event);
	}
}
