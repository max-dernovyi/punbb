<?php

declare(strict_types=1);

namespace PunBB\Module\Ranks\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in the ranks page outside the list of ranks, which an observer
 * may add markup at. The forms number their field groups, items and fields in
 * order; markup that adds any of them counts them, and the forms number on
 * from there.
 */
final class RanksRendering implements EventInterface {
	public const MAIN_OUTPUT_START = 'main_output_start';

	public const PRE_ADD_RANK_FIELDSET = 'pre_add_rank_fieldset';

	public const PRE_ADD_RANK_TITLE = 'pre_add_rank_title';

	public const PRE_ADD_RANK_MIN_POSTS = 'pre_add_rank_min_posts';

	public const PRE_ADD_RANK_SUBMIT = 'pre_add_rank_submit';

	public const PRE_ADD_RANK_FIELDSET_END = 'pre_add_rank_fieldset_end';

	public const ADD_RANK_FIELDSET_END = 'add_rank_fieldset_end';

	public const END = 'end';

	public const POSITIONS = array(
		self::MAIN_OUTPUT_START, self::PRE_ADD_RANK_FIELDSET, self::PRE_ADD_RANK_TITLE, self::PRE_ADD_RANK_MIN_POSTS, self::PRE_ADD_RANK_SUBMIT,
		self::PRE_ADD_RANK_FIELDSET_END, self::ADD_RANK_FIELDSET_END, self::END,
	);

	private string $markup = '';

	public function __construct(private readonly string $position, private int $groupCount, private int $itemCount, private int $fieldCount) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The ranks page has no position "%s"', $position));
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
