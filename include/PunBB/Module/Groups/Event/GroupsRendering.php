<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\FormMarkup;

/**
 * A position in the groups page outside a listed group, which an observer may
 * add markup at: around the forms adding a group and choosing the default one,
 * and at the end of the page. Each form numbers its field groups and items from
 * one, its fields on from the form before.
 */
final class GroupsRendering implements EventInterface {
	use FormMarkup;

	public const MAIN_OUTPUT_START = 'main_output_start';

	public const PRE_ADD_GROUP_FIELDSET = 'pre_add_group_fieldset';

	public const PRE_ADD_BASE_GROUP = 'pre_add_base_group';

	public const PRE_ADD_GROUP_FIELDSET_END = 'pre_add_group_fieldset_end';

	/** After the fieldset, before the form's button. */
	public const ADD_GROUP_FIELDSET_END = 'add_group_fieldset_end';

	public const PRE_DEFAULT_GROUP_FIELDSET = 'pre_default_group_fieldset';

	public const PRE_DEFAULT_GROUP = 'pre_default_group';

	public const PRE_DEFAULT_GROUP_FIELDSET_END = 'pre_default_group_fieldset_end';

	public const DEFAULT_GROUP_FIELDSET_END = 'default_group_fieldset_end';

	/** After the list of groups. */
	public const END = 'end';

	public const POSITIONS = array(
		self::MAIN_OUTPUT_START, self::PRE_ADD_GROUP_FIELDSET, self::PRE_ADD_BASE_GROUP, self::PRE_ADD_GROUP_FIELDSET_END, self::ADD_GROUP_FIELDSET_END,
		self::PRE_DEFAULT_GROUP_FIELDSET, self::PRE_DEFAULT_GROUP, self::PRE_DEFAULT_GROUP_FIELDSET_END, self::DEFAULT_GROUP_FIELDSET_END, self::END,
	);

	public function __construct(private readonly string $position, int $groupCount, int $itemCount, int $fieldCount) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The groups page has no position "%s"', $position));

		$this->count($groupCount, $itemCount, $fieldCount);
	}

	public function position(): string {
		return $this->position;
	}
}
