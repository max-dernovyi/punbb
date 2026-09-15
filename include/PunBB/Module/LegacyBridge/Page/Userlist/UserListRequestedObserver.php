<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Userlist;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Userlist\Event\UserListRequested;

/**
 * Runs ul_start.
 */
final class UserListRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(UserListRequested $event): void {
		$this->scope->observe('ul_start', $event);
	}
}
