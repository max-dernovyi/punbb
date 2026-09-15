<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;

/**
 * A position in the search form, which an observer may add markup at. The
 * form numbers its field groups, items and fields in order; markup that adds
 * any of them counts them, and the form numbers on from there. The links
 * above the form, the lines explaining it and the sort options may change
 * until they are shown: the first two at MAIN_OUTPUT_START, the options up to
 * PRE_SORT_BY.
 */
final class SearchFormRendering implements EventInterface {
	use PartsByName;

	public const MAIN_OUTPUT_START = 'main_output_start';

	public const PRE_CRITERIA_FIELDSET = 'pre_criteria_fieldset';

	public const PRE_KEYWORDS = 'pre_keywords';

	public const PRE_AUTHOR = 'pre_author';

	/** The advanced form only. */
	public const PRE_SEARCH_IN = 'pre_search_in';

	/** Where the form offers forums to search. */
	public const PRE_FORUM_FIELDSET = 'pre_forum_fieldset';

	public const PRE_FORUM_CHECKLIST = 'pre_forum_checklist';

	public const PRE_FORUM_FIELDSET_END = 'pre_forum_fieldset_end';

	public const FORUM_FIELDSET_END = 'forum_fieldset_end';

	public const CRITERIA_FIELDSET_END = 'criteria_fieldset_end';

	public const PRE_RESULTS_FIELDSET = 'pre_results_fieldset';

	/** The advanced form only, up to PRE_RESULTS_FIELDSET_END. */
	public const PRE_SORT_BY = 'pre_sort_by';

	public const PRE_SORT_ORDER_FIELDSET = 'pre_sort_order_fieldset';

	public const PRE_SORT_ORDER = 'pre_sort_order';

	public const PRE_SORT_ORDER_FIELDSET_END = 'pre_sort_order_fieldset_end';

	public const PRE_DISPLAY_CHOICES_FIELDSET = 'pre_display_choices_fieldset';

	public const PRE_DISPLAY_CHOICES = 'pre_display_choices';

	/** Inside the display choices, after the last. */
	public const NEW_DISPLAY_CHOICES = 'new_display_choices';

	public const PRE_DISPLAY_CHOICES_FIELDSET_END = 'pre_display_choices_fieldset_end';

	public const PRE_RESULTS_FIELDSET_END = 'pre_results_fieldset_end';

	public const RESULTS_FIELDSET_END = 'results_fieldset_end';

	public const END = 'end';

	public const POSITIONS = array(
		self::MAIN_OUTPUT_START, self::PRE_CRITERIA_FIELDSET, self::PRE_KEYWORDS, self::PRE_AUTHOR, self::PRE_SEARCH_IN,
		self::PRE_FORUM_FIELDSET, self::PRE_FORUM_CHECKLIST, self::PRE_FORUM_FIELDSET_END, self::FORUM_FIELDSET_END, self::CRITERIA_FIELDSET_END,
		self::PRE_RESULTS_FIELDSET, self::PRE_SORT_BY, self::PRE_SORT_ORDER_FIELDSET, self::PRE_SORT_ORDER, self::PRE_SORT_ORDER_FIELDSET_END,
		self::PRE_DISPLAY_CHOICES_FIELDSET, self::PRE_DISPLAY_CHOICES, self::NEW_DISPLAY_CHOICES, self::PRE_DISPLAY_CHOICES_FIELDSET_END,
		self::PRE_RESULTS_FIELDSET_END, self::RESULTS_FIELDSET_END, self::END,
	);

	/** The links above the form, joined with spaces. */
	public const HEAD_OPTIONS = 'head_options';

	/** The lines explaining the advanced form, one list item each. */
	public const INFO = 'info';

	/** The options of the sort list. */
	public const SORT = 'sort';

	private string $markup = '';

	public function __construct(
		private readonly string $position,
		private readonly bool $advanced,
		private int $groupCount,
		private int $itemCount,
		private int $fieldCount,
		Parts $headOptions,
		Parts $info,
		Parts $sort
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The search form has no position "%s"', $position));

		$this->parts = array(self::HEAD_OPTIONS => $headOptions, self::INFO => $info, self::SORT => $sort);
	}

	public function position(): string {
		return $this->position;
	}

	/** Whether the advanced form is shown. */
	public function advanced(): bool {
		return $this->advanced;
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
