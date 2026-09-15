<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Ranks;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Ranks\Event\RanksRequested;

/**
 * Loads include/common_admin.php, as admin/ranks.php did first thing, then runs ark_start.
 */
final class RanksRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(RanksRequested $event): void {
		if (!function_exists('generate_admin_menu'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/common_admin.php');

		$this->scope->observe('ark_start', $event);
	}
}
