<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Setup;

use PunBB\Module\Install\Indexing\PostIndexInterface;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;

/**
 * update_search_index() of include/search_idx.php, over the connection the installer opened.
 */
final class LegacyInstallIndex implements PostIndexInterface {
	public function index(int $postId, string $message, string $subject): void {
		if (!defined('FORUM_SEARCH_IDX_FUNCTIONS_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/search_idx.php');

		\update_search_index('post', $postId, $message, $subject);
	}
}
