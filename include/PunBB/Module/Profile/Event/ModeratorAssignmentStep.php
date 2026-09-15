<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * A step of choosing the forums a member moderates: the form submitted, before
 * the forums are read; and every forum's moderators stored, before the
 * browser is sent back to the administration section.
 */
final class ModeratorAssignmentStep implements EventInterface {
	public const SUBMITTED = 'submitted';

	public const UPDATED = 'updated';

	/** @param list<int> $forumIds the forums the member moderates, once updated */
	public function __construct(private readonly string $step, private readonly ProfileUserInterface $user, private readonly array $forumIds = array()) {
		if (!in_array($step, array(self::SUBMITTED, self::UPDATED), true))
			throw new InvalidArgumentException(sprintf('Assigning a moderator has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	/** @return list<int> */
	public function forumIds(): array {
		return $this->forumIds;
	}
}
