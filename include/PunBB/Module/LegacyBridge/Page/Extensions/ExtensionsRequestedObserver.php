<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Event\ExtensionsRequested;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Loads include/common_admin.php and include/xml.php, as admin/extensions.php
 * did first thing, then runs aex_start; the section asked for is left in
 * $section, where every later point of the page found it.
 */
final class ExtensionsRequestedObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(ExtensionsRequested $event): void {
		if (!function_exists('generate_admin_menu'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/common_admin.php');

		LegacyManifests::loadXml();

		$this->scope->observe('aex_start', $event);

		// The page read it into a variable its points saw; the events carry what they act on
		$GLOBALS['section'] = $_GET['section'] ?? null;
	}
}
