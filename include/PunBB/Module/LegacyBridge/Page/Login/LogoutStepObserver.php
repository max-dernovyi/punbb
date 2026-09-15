<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Login;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Login\Event\LogoutStep;

/**
 * Runs li_logout_selected and li_logout_pre_redirect.
 */
final class LogoutStepObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(LogoutStep $event): void {
		$this->scope->observe($event->step() === LogoutStep::SELECTED ? 'li_logout_selected' : 'li_logout_pre_redirect', $event);
	}
}
