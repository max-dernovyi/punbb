<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * A step of deleting a member from their profile: the deletion asked for,
 * before the visitor is checked; the confirmation submitted, before the member
 * is taken off the board; and the member deleted, before the browser is sent
 * to the index.
 */
final class UserDeletionStep implements EventInterface {
	public const SELECTED = 'selected';

	public const SUBMITTED = 'submitted';

	public const DELETED = 'deleted';

	/** @param bool $withPosts whether the member's posts go with them, once submitted */
	public function __construct(private readonly string $step, private readonly ProfileUserInterface $user, private readonly bool $withPosts = false) {
		if (!in_array($step, array(self::SELECTED, self::SUBMITTED, self::DELETED), true))
			throw new InvalidArgumentException(sprintf('Deleting a member has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	public function withPosts(): bool {
		return $this->withPosts;
	}
}
