<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Groups\Api\Data\GroupInterface;

/**
 * A step of adding or editing a group: the form asked for, to add a group or
 * to edit one, before the group is read; the form submitted, once the title is
 * checked; the group about to be added or edited, before its title is checked
 * against the others; and the group stored, before the browser is sent back
 * to the list.
 */
final class GroupChangeStep implements EventInterface {
	/** The form adding a group, based on another. */
	public const ADD_SELECTED = 'add_selected';

	public const EDIT_SELECTED = 'edit_selected';

	/** Carries the group as submitted, with the id the form posted. */
	public const VALIDATED = 'validated';

	public const ADDING = 'adding';

	/** Carries the group as submitted, which may still lose moderation. */
	public const EDITING = 'editing';

	public const SAVED = 'saved';

	private const STEPS = array(self::ADD_SELECTED, self::EDIT_SELECTED, self::VALIDATED, self::ADDING, self::EDITING, self::SAVED);

	public function __construct(private readonly string $step, private readonly ?GroupInterface $group = null) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Changing a group has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** The group changed; null while the form is asked for. */
	public function group(): ?GroupInterface {
		return $this->group;
	}
}
