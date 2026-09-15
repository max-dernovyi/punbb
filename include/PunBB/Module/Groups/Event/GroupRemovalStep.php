<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of removing a group: once it is selected, before its members are
 * counted; once it is about to go, with the group its members move to; and
 * once it is gone, before the browser is sent back to the list.
 */
final class GroupRemovalStep implements EventInterface {
	public const SELECTED = 'selected';

	public const REMOVING = 'removing';

	public const REMOVED = 'removed';

	/** @param ?int $movedTo the group the members move to; null when the group has none to move */
	public function __construct(private readonly string $step, private readonly int $groupId, private readonly ?int $movedTo = null) {
		if (!in_array($step, array(self::SELECTED, self::REMOVING, self::REMOVED), true))
			throw new InvalidArgumentException(sprintf('Removing a group has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function groupId(): int {
		return $this->groupId;
	}

	public function movedTo(): ?int {
		return $this->movedTo;
	}
}
