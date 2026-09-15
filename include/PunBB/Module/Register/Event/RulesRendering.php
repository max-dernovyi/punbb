<?php

declare(strict_types=1);

namespace PunBB\Module\Register\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in the forum rules a visitor agrees to before registering, which
 * an observer may add markup at. The form numbers its field group, items and
 * fields in order; markup that adds any of them counts them, and the form
 * numbers on from there.
 */
final class RulesRendering implements EventInterface {
	public const OUTPUT_START = 'output_start';

	public const PRE_GROUP = 'pre_group';

	public const PRE_AGREE_CHECKBOX = 'pre_agree_checkbox';

	public const PRE_GROUP_END = 'pre_group_end';

	public const GROUP_END = 'group_end';

	public const END = 'end';

	public const POSITIONS = array(self::OUTPUT_START, self::PRE_GROUP, self::PRE_AGREE_CHECKBOX, self::PRE_GROUP_END, self::GROUP_END, self::END);

	private string $markup = '';

	public function __construct(private readonly string $position, private int $groupCount, private int $itemCount, private int $fieldCount) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The forum rules have no position "%s"', $position));
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
