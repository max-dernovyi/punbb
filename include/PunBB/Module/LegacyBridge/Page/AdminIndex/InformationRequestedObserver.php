<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\AdminIndex;

use PunBB\Module\AdminIndex\Event\InformationRequested;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Loads include/common_admin.php, as admin/index.php did first thing, then runs ain_start.
 */
final class InformationRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(InformationRequested $event): void {
		if (!function_exists('generate_admin_menu'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/common_admin.php');

		$this->scope->observe('ain_start', $event);
	}
}
