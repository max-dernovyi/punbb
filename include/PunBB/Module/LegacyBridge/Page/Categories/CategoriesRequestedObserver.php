<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Categories;

use PunBB\Module\Categories\Event\CategoriesRequested;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Loads include/common_admin.php, as admin/categories.php did first thing, then runs acg_start.
 */
final class CategoriesRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(CategoriesRequested $event): void {
		if (!function_exists('generate_admin_menu'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/common_admin.php');

		$this->scope->observe('acg_start', $event);
	}
}
