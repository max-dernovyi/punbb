<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Search\Words\SearchWordsInterface;

/**
 * FORUM_SEARCH_MIN_WORD and validate_search_word() of include/functions.php,
 * with the extension code attached to it.
 */
final class LegacySearchWords implements SearchWordsInterface {
	/** What include/search_idx.php falls back to. */
	private const MINIMUM = 3;

	public function minimumLength(): int {
		return defined('FORUM_SEARCH_MIN_WORD') ? (int) Markers::markup(constant('FORUM_SEARCH_MIN_WORD')) : self::MINIMUM;
	}

	public function searchable(string $word): bool {
		return (bool) \validate_search_word($word);
	}
}
