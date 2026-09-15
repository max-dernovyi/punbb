<?php

declare(strict_types=1);

namespace PunBB\Module\Categories\Event;

use InvalidArgumentException;
use PunBB\Module\Categories\Api\Data\CategoryInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in a category's fieldset of the form editing the categories,
 * which an observer may add markup at. Each fieldset numbers its items from
 * one; markup that adds a group, an item or a field counts it, and the form
 * numbers on from there.
 */
final class CategoryRendering implements EventInterface {
	public const PRE_EDIT_CUR_CAT_FIELDSET = 'pre_edit_cur_cat_fieldset';

	public const PRE_EDIT_CAT_NAME = 'pre_edit_cat_name';

	public const PRE_EDIT_CAT_POSITION = 'pre_edit_cat_position';

	public const PRE_EDIT_CUR_CAT_FIELDSET_END = 'pre_edit_cur_cat_fieldset_end';

	/** After the fieldset. */
	public const EDIT_CUR_CAT_FIELDSET_END = 'edit_cur_cat_fieldset_end';

	public const POSITIONS = array(
		self::PRE_EDIT_CUR_CAT_FIELDSET, self::PRE_EDIT_CAT_NAME, self::PRE_EDIT_CAT_POSITION, self::PRE_EDIT_CUR_CAT_FIELDSET_END, self::EDIT_CUR_CAT_FIELDSET_END,
	);

	private string $markup = '';

	public function __construct(
		private readonly string $position,
		private readonly CategoryInterface $category,
		private int $groupCount,
		private int $itemCount,
		private int $fieldCount
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('A category\'s fieldset has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function category(): CategoryInterface {
		return $this->category;
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
