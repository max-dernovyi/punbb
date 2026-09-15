<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Settings;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Settings\Event\SettingsRequested;

/**
 * Loads include/common_admin.php, as admin/settings.php did first thing, then runs aop_start.
 */
final class SettingsRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(SettingsRequested $event): void {
		if (!function_exists('generate_admin_menu'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/common_admin.php');

		$this->scope->observe('aop_start', $event);
	}
}
