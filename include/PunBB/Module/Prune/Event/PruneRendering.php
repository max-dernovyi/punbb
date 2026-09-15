<?php

declare(strict_types=1);

namespace PunBB\Module\Prune\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in the pruning form or in the confirmation, which an observer may
 * add markup at. The form numbers its field groups, items and fields in order;
 * markup that adds any of them counts them, and the form numbers on from there.
 */
final class PruneRendering implements EventInterface {
	public const MAIN_OUTPUT_START = 'main_output_start';

	public const PRE_PRUNE_FIELDSET = 'pre_prune_fieldset';

	public const PRE_PRUNE_FROM = 'pre_prune_from';

	public const PRE_PRUNE_DAYS = 'pre_prune_days';

	public const PRE_PRUNE_STICKY = 'pre_prune_sticky';

	public const PRE_PRUNE_FIELDSET_END = 'pre_prune_fieldset_end';

	public const PRUNE_FIELDSET_END = 'prune_fieldset_end';

	public const END = 'end';

	/** The confirmation: before it, before its button, after it. */
	public const COMPLY_OUTPUT_START = 'comply_output_start';

	public const COMPLY_PRE_BUTTONS = 'comply_pre_buttons';

	public const COMPLY_END = 'comply_end';

	public const POSITIONS = array(
		self::MAIN_OUTPUT_START, self::PRE_PRUNE_FIELDSET, self::PRE_PRUNE_FROM, self::PRE_PRUNE_DAYS, self::PRE_PRUNE_STICKY,
		self::PRE_PRUNE_FIELDSET_END, self::PRUNE_FIELDSET_END, self::END,
		self::COMPLY_OUTPUT_START, self::COMPLY_PRE_BUTTONS, self::COMPLY_END,
	);

	private string $markup = '';

	public function __construct(private readonly string $position, private int $groupCount, private int $itemCount, private int $fieldCount) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The pruning page has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
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
