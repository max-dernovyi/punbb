<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Users\Event\UsersActionRequested;

/**
 * Runs aus_new_action. Code there that answers a request of its own writes its
 * answer and exits, as it did.
 */
final class UsersActionObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(UsersActionRequested $event): void {
		$this->scope->observe('aus_new_action', $event);
	}
}
