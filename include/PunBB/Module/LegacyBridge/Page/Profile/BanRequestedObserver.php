<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\BanRequested;

/**
 * Runs pf_ban_user_selected with the member in $user.
 */
final class BanRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(BanRequested $event): void {
		ProfileState::publish($event->user());

		$this->scope->observe('pf_ban_user_selected', $event);
	}
}
