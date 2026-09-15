<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Reports;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Reports\Event\ReportsRequested;

/**
 * Loads include/common_admin.php, as admin/reports.php did first thing, then runs arp_start.
 */
final class ReportsRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ReportsRequested $event): void {
		if (!function_exists('generate_admin_menu'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/common_admin.php');

		$this->scope->observe('arp_start', $event);
	}
}
