<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Patch;

use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Model\Setting;

/**
 * The board's options as a patch reads them when it is applied.
 */
final class BoardOptions {
	/** The option naming the character set a 1.2 board's text is converted from, while the update runs. */
	public const LEGACY_CHARSET = 'update:charset';

	/**
	 * The option naming the table a conversion is part way through, the id its next batch starts at and, while
	 * that batch is stored, the digest of each row it stores as read before conversion: 'users:400', 'users:400:401=<md5>,402=<md5>'.
	 */
	public const CONVERSION_CURSOR = 'update:converted';

	/**
	 * The option naming the options, comma-separated, that hold the digest of each configuration value and row ConvertMisc
	 * stores, as read before conversion, the key URL-encoded: 'config%2Fo_board_title=<md5>,forums%2F3=<md5>'. Written after them.
	 */
	public const MISC_DIGESTS = 'update:converted_misc';

	/** The option naming every option a write of those has claimed, stored before them so the finish removes each. */
	public const MISC_DIGESTS_WRITTEN = 'update:converted_misc_written';

	/** The most bytes one of those options holds, well inside a MySQL TEXT column. */
	private const MISC_DIGESTS_CHUNK = 32768;

	/** The option holding the id the groups' reorder parks a group at and the steps it has taken: '5:3'. */
	public const GROUP_REORDER = 'update:groups';

	/** The option naming the column ConvertTables is altering, whether its collation is binary and its type: 'users:username:0:varchar(200)'. */
	public const ALTERED_COLUMN = 'update:altering';

	/** The options that hold an update's progress, removed when it finishes; forum_config_add() refuses the 'update:' prefix. */
	public const PROGRESS = array(self::LEGACY_CHARSET, self::CONVERSION_CURSOR, self::MISC_DIGESTS, self::MISC_DIGESTS_WRITTEN, self::GROUP_REORDER, self::ALTERED_COLUMN);

	/** @return array<string, ?string> name => value */
	public static function of(BoardSettingsInterface $settings): array {
		$options = array();
		foreach ($settings->all() as $setting)
			$options[$setting->name()] = $setting->value();

		return $options;
	}

	/** @return list<string> the options holding an update's progress on this board, the ones naming others last */
	public static function progress(BoardSettingsInterface $settings): array {
		$options = self::of($settings);

		return array_merge(self::names($options, self::MISC_DIGESTS_WRITTEN, self::MISC_DIGESTS), self::PROGRESS);
	}

	/** @return array<string, string> the digests ConvertMisc recorded, key => digest; empty where it recorded none */
	public static function miscDigests(BoardSettingsInterface $settings): array {
		$options = self::of($settings);
		$digests = array();
		foreach (self::names($options, self::MISC_DIGESTS) as $name)
			foreach (array_filter(explode(',', $options[$name] ?? '')) as $entry)
			{
				[$key, $digest] = explode('=', $entry, 2) + array(1 => '');
				$digests[rawurldecode($key)] = $digest;
			}

		return $digests;
	}

	/**
	 * Records $entries ('key=digest') over as many options as they need, each one the update claimed while the board
	 * had no option of that name, the list naming them last, so an interrupted write records none.
	 *
	 * @param list<string> $entries
	 */
	public static function storeMiscDigests(BoardSettingsInterface $settings, array $entries): void {
		$chunks = array('');
		foreach ($entries as $entry)
		{
			$last = count($chunks) - 1;
			if ($chunks[$last] !== '' && strlen($chunks[$last]) + strlen($entry) + 1 > self::MISC_DIGESTS_CHUNK)
				$chunks[++$last] = '';

			$chunks[$last] .= ($chunks[$last] !== '' ? ',' : '').$entry;
		}

		$options = self::of($settings);
		$claimed = self::names($options, self::MISC_DIGESTS_WRITTEN, self::MISC_DIGESTS);
		if (count($chunks) > count($claimed))
		{
			for ($i = 1; count($claimed) < count($chunks); ++$i)
				if (!array_key_exists(self::MISC_DIGESTS.'_'.$i, $options) && !in_array(self::MISC_DIGESTS.'_'.$i, $claimed, true))
					$claimed[] = self::MISC_DIGESTS.'_'.$i;

			self::store($settings, self::MISC_DIGESTS_WRITTEN, implode(',', $claimed));
		}

		$names = array_slice($claimed, 0, count($chunks));
		foreach ($chunks as $i => $chunk)
			self::store($settings, $names[$i], $chunk);

		self::store($settings, self::MISC_DIGESTS, implode(',', $names));
	}

	/**
	 * @param array<string, ?string> $options
	 * @return list<string> the options the lists $lists name, each once
	 */
	private static function names(array $options, string ...$lists): array {
		$names = array();
		foreach ($lists as $list)
			foreach (explode(',', $options[$list] ?? '') as $name)
				if (str_starts_with($name, self::MISC_DIGESTS.'_') && !in_array($name, $names, true))
					$names[] = $name;

		return $names;
	}

	/** Stores $value under option $name in one statement, adding the option where the board has none. */
	public static function store(BoardSettingsInterface $settings, string $name, string $value): void {
		if (array_key_exists($name, self::of($settings)))
			$settings->update(new Setting($name, $value));
		else
			$settings->add(new Setting($name, $value));
	}

	/** Whether the board's database is a 1.2 one: its version moves only when the update finishes. */
	public static function from12(BoardSettingsInterface $settings): bool {
		return str_starts_with($settings->version() ?? '', '1.2');
	}

	/**
	 * The character set a 1.2 board's text is converted from, as the one
	 * starting the update named it; null when the text is not converted.
	 */
	public static function legacyCharset(BoardSettingsInterface $settings): ?string {
		if (!self::from12($settings))
			return null;

		$charset = self::of($settings)[self::LEGACY_CHARSET] ?? null;

		return $charset !== null && $charset !== '' ? $charset : null;
	}

	/**
	 * The id the next batch of $table's conversion starts at and the digests of the rows that batch stores, as
	 * the last batch recorded them; null where none is.
	 *
	 * @return ?array{int, array<int, string>}
	 */
	public static function conversionCursor(BoardSettingsInterface $settings, string $table): ?array {
		$cursor = self::of($settings)[self::CONVERSION_CURSOR] ?? '';
		if (!str_starts_with($cursor, $table.':'))
			return null;

		$parts = explode(':', substr($cursor, strlen($table) + 1), 2);
		$digests = array();
		foreach (array_filter(explode(',', $parts[1] ?? '')) as $entry)
		{
			[$id, $digest] = explode('=', $entry, 2) + array(1 => '');
			$digests[(int) $id] = $digest;
		}

		return array((int) $parts[0], $digests);
	}
}
