<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Censoring;

use PunBB\Module\Censoring\Event\CensoringRequested;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Loads include/common_admin.php, as admin/censoring.php did first thing, then runs acs_start.
 */
final class CensoringRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(CensoringRequested $event): void {
		if (!function_exists('generate_admin_menu'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/common_admin.php');

		$this->scope->observe('acs_start', $event);
	}
}
