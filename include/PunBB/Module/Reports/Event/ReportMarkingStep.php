<?php

declare(strict_types=1);

namespace PunBB\Module\Reports\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of marking reports read: the form submitted with reports selected,
 * and the reports marked, before the browser is sent back to the list.
 */
final class ReportMarkingStep implements EventInterface {
	/** Reports are selected; nothing was marked yet. */
	public const SUBMITTED = 'submitted';

	/** The reports are marked read and the flash message is set. */
	public const MARKED = 'marked';

	private const STEPS = array(self::SUBMITTED, self::MARKED);

	/** @param list<int> $reportIds the reports selected */
	public function __construct(private readonly string $step, private readonly array $reportIds) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Marking reports read has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** @return list<int> */
	public function reportIds(): array {
		return $this->reportIds;
	}
}
