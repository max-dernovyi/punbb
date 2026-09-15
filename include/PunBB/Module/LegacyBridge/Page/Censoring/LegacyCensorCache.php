<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Censoring;

use PunBB\Module\Censoring\Cache\CensorCacheInterface;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;

/**
 * generate_censors_cache() of include/cache.php, with the extension code attached to it.
 */
final class LegacyCensorCache implements CensorCacheInterface {
	public function rebuild(): void {
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/cache.php');

		\generate_censors_cache();
	}
}
