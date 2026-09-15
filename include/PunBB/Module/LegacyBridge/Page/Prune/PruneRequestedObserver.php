<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Prune;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Prune\Event\PruneRequested;

/**
 * Loads include/common_admin.php, as admin/prune.php did first thing, then runs apr_start.
 */
final class PruneRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(PruneRequested $event): void {
		if (!function_exists('generate_admin_menu'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/common_admin.php');

		$this->scope->observe('apr_start', $event);
	}
}
