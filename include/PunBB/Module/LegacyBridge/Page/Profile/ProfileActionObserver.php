<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\ProfileActionRequested;

/**
 * Runs pf_new_action with the member in $user, the section in $section and
 * the action in $action. Code there that answers a request of its own writes
 * its answer and exits, as it did.
 */
final class ProfileActionObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ProfileActionRequested $event): void {
		ProfileState::publish($event->user());
		$GLOBALS['section'] = $event->section();
		$GLOBALS['action'] = $event->action();

		$this->scope->observe('pf_new_action', $event);
	}
}
