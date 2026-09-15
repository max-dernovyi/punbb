<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page;

use PunBB\Module\LegacyBridge\Layout\Markers;

/**
 * $forum_page, where a page script kept what its points read and changed: a
 * key published before a point runs, and read back once it has.
 */
final class ForumPage {
	/** @return array<mixed> */
	public static function all(): array {
		return isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
	}

	public static function set(string $key, mixed $value): void {
		$page = self::all();
		$page[$key] = $value;
		$GLOBALS['forum_page'] = $page;
	}

	public static function get(string $key): mixed {
		return self::all()[$key] ?? null;
	}

	/** The form's field groups, items and fields numbered so far, as a page script counted them. */
	public static function publishCounts(int $groups, int $items, int $fields): void {
		$page = self::all();
		$page['group_count'] = $groups;
		$page['item_count'] = $items;
		$page['fld_count'] = $fields;
		$GLOBALS['forum_page'] = $page;
	}

	/**
	 * The counts as extension code left them, each the one given where it left none.
	 *
	 * @return array{int, int, int}
	 */
	public static function counts(int $groups, int $items, int $fields): array {
		$page = self::all();

		return array(
			(int) Markers::markup($page['group_count'] ?? $groups),
			(int) Markers::markup($page['item_count'] ?? $items),
			(int) Markers::markup($page['fld_count'] ?? $fields),
		);
	}
}
