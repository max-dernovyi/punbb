<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extensions;

use PunBB\Module\Extensions\Cache\ExtensionCacheInterface;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;

/**
 * forum_clear_cache() of include/functions.php and generate_hooks_cache() of
 * include/cache.php, with the extension code attached to them.
 */
final class LegacyExtensionCache implements ExtensionCacheInterface {
	public function clear(): void {
		\forum_clear_cache();
	}

	public function rebuildHooks(): void {
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/cache.php');

		\generate_hooks_cache();
	}
}
