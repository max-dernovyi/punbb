<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Users\Event\UsersRequested;

/**
 * Loads include/common_admin.php, as admin/users.php did first thing, then runs aus_start.
 */
final class UsersRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(UsersRequested $event): void {
		if (!function_exists('generate_admin_menu'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/common_admin.php');

		$this->scope->observe('aus_start', $event);
	}
}
