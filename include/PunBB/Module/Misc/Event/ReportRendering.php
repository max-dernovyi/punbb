<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;

/**
 * A position in the form reporting a post, which an observer may add markup at. The form
 * numbers its field groups, items and fields in order; markup that adds any of
 * them counts them, and the form numbers on from there. At the start the
 * form's hidden fields, and before the errors the errors, each named, may
 * still change; the errors are there only when something stopped the report.
 */
final class ReportRendering implements EventInterface {
	use PartsByName;

	public const OUTPUT_START = 'output_start';

	public const PRE_REPORT_ERRORS = 'pre_report_errors';

	public const PRE_FIELDSET = 'pre_fieldset';

	public const PRE_REASON = 'pre_reason';

	public const PRE_FIELDSET_END = 'pre_fieldset_end';

	public const FIELDSET_END = 'fieldset_end';

	public const END = 'end';

	public const POSITIONS = array(
		self::OUTPUT_START, self::PRE_REPORT_ERRORS, self::PRE_FIELDSET, self::PRE_REASON, self::PRE_FIELDSET_END, self::FIELDSET_END, self::END,
	);

	/** The groups of parts the event carries. */
	public const HIDDEN_FIELDS = 'hidden_fields';

	public const ERRORS = 'errors';

	private string $markup = '';

	/** @param string $action the URL the form posts to */
	public function __construct(
		private readonly string $position,
		private readonly string $action,
		private int $groupCount,
		private int $itemCount,
		private int $fieldCount,
		?Parts $hiddenFields = null,
		?Parts $errors = null
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The report form has no position "%s"', $position));

		$this->parts = array(
			self::HIDDEN_FIELDS	=> $hiddenFields ?? new Parts(),
			self::ERRORS		=> $errors ?? new Parts(),
		);
	}

	public function position(): string {
		return $this->position;
	}

	public function action(): string {
		return $this->action;
	}

	public function groupCount(): int {
		return $this->groupCount;
	}

	public function itemCount(): int {
		return $this->itemCount;
	}

	public function fieldCount(): int {
		return $this->fieldCount;
	}

	/** The form's counts once the markup added here is counted in. */
	public function count(int $groups, int $items, int $fields): void {
		$this->groupCount = $groups;
		$this->itemCount = $items;
		$this->fieldCount = $fields;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
