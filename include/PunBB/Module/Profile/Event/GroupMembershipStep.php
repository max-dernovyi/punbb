<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * A step of moving a member into another group from their profile: the form
 * submitted, before the group is read; and the member moved, before the
 * browser is sent back to the administration section.
 */
final class GroupMembershipStep implements EventInterface {
	public const SUBMITTED = 'submitted';

	public const CHANGED = 'changed';

	/** @param int $groupId the group the member moved into, once changed */
	public function __construct(private readonly string $step, private readonly ProfileUserInterface $user, private readonly int $groupId = 0) {
		if (!in_array($step, array(self::SUBMITTED, self::CHANGED), true))
			throw new InvalidArgumentException(sprintf('Changing a member\'s group has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	public function groupId(): int {
		return $this->groupId;
	}
}
