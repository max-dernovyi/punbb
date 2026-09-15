<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Bans;

use PunBB\Module\Bans\Event\BansRequested;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Loads include/common_admin.php, as admin/bans.php did first thing, then runs aba_start.
 */
final class BansRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(BansRequested $event): void {
		if (!function_exists('generate_admin_menu'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/common_admin.php');

		$this->scope->observe('aba_start', $event);
	}
}
