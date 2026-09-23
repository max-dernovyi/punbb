<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Patch;

use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Api\ConversionInterface;
use PunBB\Module\Update\Api\Data\TextRowInterface;
use PunBB\Module\Update\Charset\Utf8Text;
use PunBB\Module\Update\Model\TextRow;

/**
 * A 1.2 board's text in $table converted to UTF-8, a batch of rows at a time,
 * where the update was asked to convert it.
 */
final class ConvertRows implements DataPatchInterface {
	/** The rows a batch handles: lower it where a batch times out. */
	public const PER_PAGE = 300;

	/**
	 * @param list<string> $columns
	 * @param list<string> $nullable the columns stored as NULL where they end up empty
	 * @param string $label what a row is, in the lines: 'user'
	 * @param ?int $from the id the first batch starts at; null for the table's lowest
	 */
	public function __construct(
		private readonly BoardSettingsInterface $settings,
		private readonly ConversionInterface $conversion,
		private readonly DatabaseInterface $database,
		private readonly string $table,
		private readonly array $columns,
		private readonly array $nullable,
		private readonly string $label,
		private readonly ?int $from = null
	) {}

	public function apply(int $startAt): PatchStep {
		$charset = BoardOptions::legacyCharset($this->settings);
		if ($charset === null)
			return new PatchStep();

		$this->database->setNames('utf8');

		// A row is converted once: decoding entities again would change the text it holds
		$stored = array();
		$cursor = BoardOptions::conversionCursor($this->settings, $this->table);
		if ($cursor !== null && $cursor[0] >= $startAt)
			[$startAt, $stored] = $cursor;
		else if ($startAt === 0)
			$startAt = $this->from ?? $this->conversion->firstId($this->table) ?? 0;

		$endAt = $startAt + self::PER_PAGE;

		// Converted before any is stored, so text the charset cannot convert leaves the batch unwritten
		$lines = array();
		$rows = array();
		$read = array();
		foreach ($this->conversion->rows($this->table, 'id', $this->columns, $startAt, $endAt) as $row)
		{
			$lines[] = sprintf('Converting %s %d…', $this->label, $row->id());

			// No longer as read, so stored by an interrupted run of this batch, however MySQL coerced the value
			$digest = self::digest($row, $this->columns);
			if (isset($stored[$row->id()]) && $stored[$row->id()] !== $digest)
				continue;

			$converted = self::converted($row, $this->columns, $this->nullable, $charset);
			if ($converted !== null)
			{
				$rows[] = $converted;
				$read[] = $row->id().'='.$digest;
			}
		}

		if ($rows !== array() && $stored === array())
			$this->saveCursor($startAt.':'.implode(',', $read));

		foreach ($rows as $row)
			$this->conversion->store($this->table, 'id', $row);

		$next = $this->conversion->nextId($this->table, $endAt);

		// Kept past the last batch, so a run that stops before the patch is recorded converts nothing again
		$this->saveCursor((string) ($next ?? $endAt));

		return new PatchStep($lines, $next);
	}

	/** @param list<string> $columns */
	private static function digest(TextRowInterface $row, array $columns): string {
		return md5(serialize(array_map($row->value(...), $columns)));
	}

	/** Written in one statement: a cursor missing between two would convert the table again from its first row. */
	private function saveCursor(string $position): void {
		BoardOptions::store($this->settings, BoardOptions::CONVERSION_CURSOR, $this->table.':'.$position);
	}

	/**
	 * $row with each of $columns converted, each of $nullable NULL where it is
	 * empty; null when no column changed.
	 *
	 * @param list<string> $columns
	 * @param list<string> $nullable
	 */
	public static function converted(TextRowInterface $row, array $columns, array $nullable, string $charset): ?TextRow {
		$values = array();
		$changed = false;

		foreach ($columns as $column)
		{
			$converted = Utf8Text::convert($row->value($column), $charset);
			$changed = $changed || $converted !== null;
			$value = $converted ?? $row->value($column);

			$values[$column] = in_array($column, $nullable, true) && ($value ?? '') === '' ? null : ($value ?? '');
		}

		return $changed ? new TextRow($row->id(), $values) : null;
	}
}
