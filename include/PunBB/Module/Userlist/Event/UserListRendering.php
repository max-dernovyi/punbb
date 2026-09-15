<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Userlist\Api\Data\MemberSearchInterface;

/**
 * A position in the member list, which an observer may add markup at. The
 * search form numbers its field groups, items and fields in order; markup that
 * adds any of them counts them, and the form numbers on from there.
 */
final class UserListRendering implements EventInterface {
	public const MAIN_OUTPUT_START = 'main_output_start';

	public const SEARCH_FIELDSET_START = 'search_fieldset_start';

	public const PRE_USERNAME = 'pre_username';

	public const PRE_GROUP_SELECT = 'pre_group_select';

	/** Inside the group list, after "All users". */
	public const SEARCH_NEW_GROUP_OPTION = 'search_new_group_option';

	public const PRE_SORT_BY = 'pre_sort_by';

	/** Inside the sort list, after its last option. */
	public const NEW_SORT_BY_OPTION = 'new_sort_by_option';

	public const PRE_SORT_ORDER_FIELDSET = 'pre_sort_order_fieldset';

	public const PRE_SORT_ORDER = 'pre_sort_order';

	public const PRE_SORT_ORDER_FIELDSET_END = 'pre_sort_order_fieldset_end';

	public const PRE_SEARCH_FIELDSET_END = 'pre_search_fieldset_end';

	public const SEARCH_FIELDSET_END = 'search_fieldset_end';

	/** Before the table of members; reached only when there are members to list. */
	public const RESULTS_PRE_HEADER = 'results_pre_header';

	public const END = 'end';

	public const POSITIONS = array(
		self::MAIN_OUTPUT_START, self::SEARCH_FIELDSET_START, self::PRE_USERNAME, self::PRE_GROUP_SELECT, self::SEARCH_NEW_GROUP_OPTION,
		self::PRE_SORT_BY, self::NEW_SORT_BY_OPTION, self::PRE_SORT_ORDER_FIELDSET, self::PRE_SORT_ORDER, self::PRE_SORT_ORDER_FIELDSET_END,
		self::PRE_SEARCH_FIELDSET_END, self::SEARCH_FIELDSET_END, self::RESULTS_PRE_HEADER, self::END,
	);

	private string $markup = '';

	public function __construct(
		private readonly string $position,
		private readonly MemberSearchInterface $search,
		private int $groupCount,
		private int $itemCount,
		private int $fieldCount
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The member list has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function search(): MemberSearchInterface {
		return $this->search;
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
