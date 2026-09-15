<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page;

use PunBB\Module\LegacyBridge\Layout\Markers;

/**
 * The name a page script's query read with $forum_db->result(): the first column of its first row.
 */
final class FoundName {
	/**
	 * The name, null when the query found no row.
	 *
	 * @param array<string, mixed> $query
	 */
	public static function of(array $query): ?string {
		$value = PluggedQuery::value($query);

		return $value !== null && $value !== false ? Markers::markup($value) : null;
	}
}
