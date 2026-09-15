<?php

declare(strict_types=1);

namespace PunBB\Module\Categories\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * A position in the categories page outside a category's own fieldset, which
 * an observer may add markup at: around the forms adding, deleting and editing
 * categories. The forms number their field groups, items and fields in order;
 * markup that adds any of them counts them, and the forms number on from
 * there. At the start the hidden fields the three forms carry, named, may
 * still change.
 */
final class CategoriesRendering implements EventInterface {
	use MarkupEntries;

	public const MAIN_OUTPUT_START = 'main_output_start';

	public const PRE_ADD_CAT_FIELDSET = 'pre_add_cat_fieldset';

	public const PRE_NEW_CATEGORY_NAME = 'pre_new_category_name';

	public const PRE_NEW_CATEGORY_POSITION = 'pre_new_category_position';

	public const PRE_ADD_CAT_FIELDSET_END = 'pre_add_cat_fieldset_end';

	public const ADD_CAT_FIELDSET_END = 'add_cat_fieldset_end';

	/** After the form adding a category; the next form numbers its groups and items from one again. */
	public const POST_ADD_CAT_FORM = 'post_add_cat_form';

	/** The form deleting a category and the one editing them are there only when a category is. */
	public const PRE_DEL_CAT_FIELDSET = 'pre_del_cat_fieldset';

	public const PRE_DEL_CATEGORY_SELECT = 'pre_del_category_select';

	public const PRE_DEL_CAT_FIELDSET_END = 'pre_del_cat_fieldset_end';

	public const DEL_CAT_FIELDSET_END = 'del_cat_fieldset_end';

	public const POST_DEL_CAT_FORM = 'post_del_cat_form';

	public const EDIT_CAT_FIELDSETS_START = 'edit_cat_fieldsets_start';

	public const EDIT_CAT_FIELDSETS_END = 'edit_cat_fieldsets_end';

	public const POST_EDIT_CAT_FORM = 'post_edit_cat_form';

	public const END = 'end';

	public const POSITIONS = array(
		self::MAIN_OUTPUT_START, self::PRE_ADD_CAT_FIELDSET, self::PRE_NEW_CATEGORY_NAME, self::PRE_NEW_CATEGORY_POSITION, self::PRE_ADD_CAT_FIELDSET_END,
		self::ADD_CAT_FIELDSET_END, self::POST_ADD_CAT_FORM, self::PRE_DEL_CAT_FIELDSET, self::PRE_DEL_CATEGORY_SELECT, self::PRE_DEL_CAT_FIELDSET_END,
		self::DEL_CAT_FIELDSET_END, self::POST_DEL_CAT_FORM, self::EDIT_CAT_FIELDSETS_START, self::EDIT_CAT_FIELDSETS_END, self::POST_EDIT_CAT_FORM, self::END,
	);

	private string $markup = '';

	/**
	 * @param string $action the URL the forms post to
	 * @param array<string, string> $hiddenFields
	 */
	public function __construct(
		private readonly string $position,
		private readonly string $action,
		array $hiddenFields,
		private int $groupCount,
		private int $itemCount,
		private int $fieldCount
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The categories page has no position "%s"', $position));

		$this->entries = $hiddenFields;
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

	/** The forms' counts once the markup added here is counted in. */
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

	private function accept(string $name): void {
		if ($this->position !== self::MAIN_OUTPUT_START)
			throw new InvalidArgumentException('The categories page\'s hidden fields are placed by its start');
	}
}
