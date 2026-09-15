<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Moderate;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\Moderate\Indexing\PostIndexInterface;

/**
 * strip_search_index() of include/search_idx.php, with the extension code attached to it.
 */
final class LegacyIndexStrip implements PostIndexInterface {
	public function strip(int ...$postIds): void {
		if (!defined('FORUM_SEARCH_IDX_FUNCTIONS_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/search_idx.php');

		\strip_search_index($postIds);
	}
}
