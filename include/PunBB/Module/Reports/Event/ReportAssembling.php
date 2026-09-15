<?php

declare(strict_types=1);

namespace PunBB\Module\Reports\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;
use PunBB\Module\Reports\Api\Data\ReportInterface;

/**
 * A stage of a report's block on the reports page: its parts before the block
 * is placed, and the end of the block. Each part is markup: who reported it,
 * its forum, topic and post, its message and, once read, who marked it. The
 * blocks and the checkboxes are numbered in order; markup that adds either
 * counts it, and the page numbers on from there. Markup appended at the parts
 * stage goes before the block, at the end stage inside it.
 */
final class ReportAssembling implements EventInterface {
	use MarkupEntries;

	/** The parts, before the block is placed. */
	public const PARTS = 'parts';

	/** Inside the block, after its lines. */
	public const BLOCK_END = 'block_end';

	/** The names of the parts. */
	public const PART_NAMES = array('reporter', 'forum', 'topic', 'post', 'message', 'zapped_by');

	private const STAGES = array(self::PARTS, self::BLOCK_END);

	private string $markup = '';

	/**
	 * @param int $number the report's place in its list, from one
	 * @param array<string, string> $parts
	 */
	public function __construct(
		private readonly string $stage,
		private readonly ReportInterface $report,
		private readonly int $number,
		array $parts,
		private int $itemCount,
		private int $fieldCount
	) {
		if (!in_array($stage, self::STAGES, true))
			throw new InvalidArgumentException(sprintf('A report\'s block has no stage "%s"', $stage));

		$this->entries = $parts;
	}

	public function stage(): string {
		return $this->stage;
	}

	public function report(): ReportInterface {
		return $this->report;
	}

	/** The report's place in its list, from one. */
	public function number(): int {
		return $this->number;
	}

	/** Whether the report was marked read, and is listed among those. */
	public function isRead(): bool {
		return $this->report->zapped() !== null;
	}

	/** The blocks numbered so far. */
	public function itemCount(): int {
		return $this->itemCount;
	}

	/** The checkboxes numbered so far. */
	public function fieldCount(): int {
		return $this->fieldCount;
	}

	public function count(int $items, int $fields): void {
		$this->itemCount = $items;
		$this->fieldCount = $fields;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}

	private function accept(string $name): void {
		if (!in_array($name, self::PART_NAMES, true))
			throw new InvalidArgumentException(sprintf('A report has no part "%s"', $name));
	}
}
