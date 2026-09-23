<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Patch;

use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Api\ConversionInterface;
use PunBB\Module\Update\Charset\Utf8Text;
use PunBB\Module\Update\Model\Setting;
use PunBB\Module\Update\Model\TextRow;

/**
 * A 1.2 board's configuration, categories, forums, groups, ranks and censored
 * words converted to UTF-8 at once, where the update was asked to convert them.
 */
final class ConvertMisc implements DataPatchInterface {
	public function __construct(
		private readonly BoardSettingsInterface $settings,
		private readonly ConversionInterface $conversion,
		private readonly DatabaseInterface $database
	) {}

	public function apply(int $startAt): PatchStep {
		$charset = BoardOptions::legacyCharset($this->settings);
		if ($charset === null)
			return new PatchStep();

		$this->database->setNames('utf8');

		// A value is converted once: decoding entities again would change the text it holds
		$stored = BoardOptions::miscDigests($this->settings);

		// Converted before any is stored, so text the charset cannot convert leaves everything unwritten
		$lines = array('Converting configuration…');
		$settings = array();
		$digests = array();
		foreach (BoardOptions::of($this->settings) as $name => $value)
		{
			$converted = Utf8Text::convert($value, $charset);
			if ($converted !== null && self::pending($stored, $digests, 'config/'.$name, array($value)))
				$settings[] = new Setting($name, $converted);
		}

		$lines[] = 'Converting categories…';
		$rows = $this->convertAll('categories', 'id', array('cat_name'), array(), $charset, $stored, $digests);

		$lines[] = 'Converting forums…';
		$columns = array('forum_name', 'forum_desc', 'moderators');
		foreach ($this->conversion->rows('forums', 'id', $columns) as $forum)
		{
			$moderators = self::moderators($forum->value('moderators'));
			$converted = array();
			foreach ($moderators as $username => $userId)
				$converted[Utf8Text::convert((string) $username, $charset) ?? (string) $username] = $userId;

			$name = Utf8Text::convert($forum->value('forum_name'), $charset);
			$description = Utf8Text::convert($forum->value('forum_desc'), $charset) ?? $forum->value('forum_desc');

			if (($name !== null || $description !== $forum->value('forum_desc') || $converted !== $moderators)
				&& self::pending($stored, $digests, 'forums/'.$forum->id(), array_map($forum->value(...), $columns)))
				$rows[] = array('forums', 'id', new TextRow($forum->id(), array(
					'forum_name'	=> $name ?? $forum->value('forum_name') ?? '',
					'forum_desc'	=> $description !== '' ? $description : null,
					'moderators'	=> $converted !== array() ? serialize($converted) : null,
				)));
		}

		$lines[] = 'Converting groups…';
		$rows = array_merge($rows, $this->convertAll('groups', 'g_id', array('g_title', 'g_user_title'), array('g_user_title'), $charset, $stored, $digests));

		$lines[] = 'Converting ranks…';
		$rows = array_merge($rows, $this->convertAll('ranks', 'id', array('rank'), array(), $charset, $stored, $digests));

		$lines[] = 'Converting censor words…';
		$rows = array_merge($rows, $this->convertAll('censoring', 'id', array('search_for', 'replace_with'), array(), $charset, $stored, $digests));

		if ($digests !== array() && $stored === array())
			BoardOptions::storeMiscDigests($this->settings, $digests);

		if ($settings !== array())
			$this->settings->update(...$settings);

		foreach ($rows as [$table, $idColumn, $row])
			$this->conversion->store($table, $idColumn, $row);

		return new PatchStep($lines);
	}

	/**
	 * Every row of $table converted that is still to be stored.
	 *
	 * @param list<string> $columns
	 * @param list<string> $nullable the columns stored as NULL where they end up empty
	 * @param array<string, string> $stored
	 * @param list<string> $digests
	 * @return list<array{string, string, TextRow}> table, id column, row
	 */
	private function convertAll(string $table, string $idColumn, array $columns, array $nullable, string $charset, array $stored, array &$digests): array {
		$rows = array();
		foreach ($this->conversion->rows($table, $idColumn, $columns) as $row)
		{
			$converted = ConvertRows::converted($row, $columns, $nullable, $charset);
			if ($converted !== null && self::pending($stored, $digests, $table.'/'.$row->id(), array_map($row->value(...), $columns)))
				$rows[] = array($table, $idColumn, $converted);
		}

		return $rows;
	}

	/**
	 * Whether the value under $key, as read, is still to be stored: it is not when an interrupted run stored it and
	 * it no longer reads as that run read it. Its digest joins $digests.
	 *
	 * @param array<string, string> $stored
	 * @param list<string> $digests
	 * @param list<?string> $values
	 */
	private static function pending(array $stored, array &$digests, string $key, array $values): bool {
		$digest = md5(serialize($values));
		if (isset($stored[$key]) && $stored[$key] !== $digest)
			return false;

		$digests[] = rawurlencode($key).'='.$digest;

		return true;
	}

	/** @return array<array-key, mixed> the moderators a forum stored, username => id */
	private static function moderators(?string $stored): array {
		if ($stored === null || $stored === '')
			return array();

		$moderators = unserialize($stored, array('allowed_classes' => false));

		return is_array($moderators) ? $moderators : array();
	}
}
