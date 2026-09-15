<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Reindex;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Reindex\Event\ReindexRequested;

/**
 * Loads include/common_admin.php, as admin/reindex.php did first thing, then runs ari_start.
 */
final class ReindexRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ReindexRequested $event): void {
		if (!function_exists('generate_admin_menu'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/common_admin.php');

		$this->scope->observe('ari_start', $event);
	}
}
