<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Edit;

use PunBB\Module\Edit\Indexing\EditIndexInterface;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;

/**
 * update_search_index() of include/search_idx.php in its edit mode, with the extension code attached to it.
 */
final class LegacyEditIndex implements EditIndexInterface {
	public function update(int $postId, string $message, ?string $subject): void {
		if (!defined('FORUM_SEARCH_IDX_FUNCTIONS_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/search_idx.php');

		if ($subject !== null)
			\update_search_index('edit', $postId, $message, $subject);
		else
			\update_search_index('edit', $postId, $message);
	}
}
