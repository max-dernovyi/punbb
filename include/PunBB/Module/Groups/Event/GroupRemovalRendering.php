<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Groups\Api\Data\GroupMembersInterface;
use PunBB\Module\Layout\Event\FormMarkup;

/**
 * A position in the form removing a group with members, which asks where they
 * move to, which an observer may add markup at. The form numbers its field
 * groups, items and fields in order.
 */
final class GroupRemovalRendering implements EventInterface {
	use FormMarkup;

	public const OUTPUT_START = 'output_start';

	public const PRE_DEL_FIELDSET = 'pre_del_fieldset';

	public const PRE_MOVE_TO_GROUP = 'pre_move_to_group';

	public const PRE_DEL_FIELDSET_END = 'pre_del_fieldset_end';

	/** After the fieldset, before the form's buttons. */
	public const DEL_FIELDSET_END = 'del_fieldset_end';

	public const END = 'end';

	public const POSITIONS = array(self::OUTPUT_START, self::PRE_DEL_FIELDSET, self::PRE_MOVE_TO_GROUP, self::PRE_DEL_FIELDSET_END, self::DEL_FIELDSET_END, self::END);

	public function __construct(private readonly string $position, private readonly int $groupId, private readonly GroupMembersInterface $members, int $groupCount, int $itemCount, int $fieldCount) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The form removing a group has no position "%s"', $position));

		$this->count($groupCount, $itemCount, $fieldCount);
	}

	public function position(): string {
		return $this->position;
	}

	public function groupId(): int {
		return $this->groupId;
	}

	/** The group's title and member count. */
	public function members(): GroupMembersInterface {
		return $this->members;
	}
}
