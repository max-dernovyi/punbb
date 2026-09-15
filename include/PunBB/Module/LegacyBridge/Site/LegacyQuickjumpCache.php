<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\Site\Cache\QuickjumpCacheInterface;

/**
 * generate_quickjump_cache() and clean_quickjump_cache() of include/cache.php,
 * with the extension code attached to them.
 */
final class LegacyQuickjumpCache implements QuickjumpCacheInterface {
	public function rebuild(): void {
		self::load();

		\generate_quickjump_cache();
	}

	public function clear(): void {
		self::load();

		\clean_quickjump_cache();
	}

	private static function load(): void {
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/cache.php');
	}
}
