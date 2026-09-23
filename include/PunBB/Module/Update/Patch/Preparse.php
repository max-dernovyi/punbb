<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Patch;

use PunBB\Module\Database\Patch\DataPatchInterface;
use PunBB\Module\Database\Patch\PatchStep;
use PunBB\Module\Setup\Database\DatabaseInterface;
use PunBB\Module\Update\Api\BoardSettingsInterface;
use PunBB\Module\Update\Api\ConversionInterface;
use PunBB\Module\Update\Model\TextRow;
use PunBB\Module\Update\Parsing\PreparserInterface;

/**
 * A 1.2 board's posts or signatures preparsed as this release stores them, a
 * batch of rows at a time.
 */
final class Preparse implements DataPatchInterface {
	/**
	 * @param string $label what the lines say for a row: 'Preparsing post'
	 * @param ?int $from the id the first batch starts at; null for the table's lowest
	 */
	public function __construct(
		private readonly BoardSettingsInterface $settings,
		private readonly ConversionInterface $conversion,
		private readonly DatabaseInterface $database,
		private readonly PreparserInterface $preparser,
		private readonly string $table,
		private readonly string $column,
		private readonly bool $signature,
		private readonly string $label,
		private readonly ?int $from = null
	) {}

	public function apply(int $startAt): PatchStep {
		if (!BoardOptions::from12($this->settings))
			return new PatchStep();

		// Definitely UTF-8 from here on
		$this->database->setNames('utf8');

		if ($startAt === 0)
			$startAt = $this->from ?? $this->conversion->firstId($this->table) ?? 0;

		$endAt = $startAt + ConvertRows::PER_PAGE;

		$lines = array();
		foreach ($this->conversion->rows($this->table, 'id', array($this->column), $startAt, $endAt) as $row)
		{
			$lines[] = sprintf('%s %d…', $this->label, $row->id());
			$this->conversion->store($this->table, 'id', new TextRow($row->id(), array($this->column => $this->preparser->preparse($row->value($this->column) ?? '', $this->signature))));
		}

		return new PatchStep($lines, $this->conversion->nextId($this->table, $endAt));
	}
}
