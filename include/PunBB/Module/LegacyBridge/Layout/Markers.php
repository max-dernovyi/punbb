<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout;

/**
 * The legacy template markers: region "title" is <!-- forum_title -->.
 */
final class Markers {
	public static function of(string $region): string {
		return '<!-- forum_'.$region.' -->';
	}

	/**
	 * @param array<string, string> $regions
	 * @return array<string, string> marker => markup
	 */
	public static function keyed(array $regions): array {
		$keyed = array();
		foreach ($regions as $name => $markup)
			$keyed[self::of($name)] = $markup;

		return $keyed;
	}

	/**
	 * What a hook left in a variable that held an array of markup, read back
	 * as one: anything else is an empty array. A name PHP holds as an integer,
	 * as $forum_head[] leaves it, comes back as one: cast it before a string
	 * parameter takes it.
	 *
	 * @return array<array-key, string>
	 */
	public static function entries(mixed $value): array {
		$entries = array();
		foreach (is_array($value) ? $value : array() as $name => $markup)
			$entries[$name] = self::markup($markup);

		return $entries;
	}

	/** Markup a hook left in a legacy array, as a string the way str_replace() would take it. */
	public static function markup(mixed $value): string {
		return is_scalar($value) ? (string) $value : '';
	}
}
