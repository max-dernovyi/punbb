<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\Site\Cache\ConfigCacheInterface;

/**
 * generate_config_cache() of include/cache.php, with the extension code attached to it.
 */
final class LegacyConfigCache implements ConfigCacheInterface {
	public function rebuild(): void {
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/cache.php');

		\generate_config_cache();
	}
}
