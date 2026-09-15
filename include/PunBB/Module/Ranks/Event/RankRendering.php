<?php

declare(strict_types=1);

namespace PunBB\Module\Ranks\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Ranks\Api\Data\RankInterface;

/**
 * A position in a stored rank's fieldset of the form editing the ranks, which
 * an observer may add markup at. The form numbers its items and fields in
 * order; markup that adds either counts it, and the form numbers on from there.
 */
final class RankRendering implements EventInterface {
	public const PRE_EDIT_CUR_RANK_FIELDSET = 'pre_edit_cur_rank_fieldset';

	public const PRE_EDIT_CUR_RANK_TITLE = 'pre_edit_cur_rank_title';

	public const PRE_EDIT_CUR_RANK_MIN_POSTS = 'pre_edit_cur_rank_min_posts';

	public const PRE_EDIT_CUR_RANK_SUBMIT = 'pre_edit_cur_rank_submit';

	public const PRE_EDIT_CUR_RANK_FIELDSET_END = 'pre_edit_cur_rank_fieldset_end';

	/** After the fieldset. */
	public const EDIT_CUR_RANK_FIELDSET_END = 'edit_cur_rank_fieldset_end';

	public const POSITIONS = array(
		self::PRE_EDIT_CUR_RANK_FIELDSET, self::PRE_EDIT_CUR_RANK_TITLE, self::PRE_EDIT_CUR_RANK_MIN_POSTS, self::PRE_EDIT_CUR_RANK_SUBMIT,
		self::PRE_EDIT_CUR_RANK_FIELDSET_END, self::EDIT_CUR_RANK_FIELDSET_END,
	);

	private string $markup = '';

	/** @param int $number the rank's place in the list, from 1 */
	public function __construct(
		private readonly string $position,
		private readonly RankInterface $rank,
		private readonly int $number,
		private int $groupCount,
		private int $itemCount,
		private int $fieldCount
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('A rank\'s fieldset has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function rank(): RankInterface {
		return $this->rank;
	}

	public function number(): int {
		return $this->number;
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
