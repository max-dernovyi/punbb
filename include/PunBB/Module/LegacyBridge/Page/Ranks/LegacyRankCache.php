<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Ranks;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\Ranks\Cache\RankCacheInterface;

/**
 * generate_ranks_cache() of include/cache.php, with the extension code attached to it.
 */
final class LegacyRankCache implements RankCacheInterface {
	public function rebuild(): void {
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/cache.php');

		\generate_ranks_cache();
	}
}
