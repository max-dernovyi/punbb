<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * A step of deleting a member's avatar: the link followed with its token,
 * before the avatar is deleted; and the avatar deleted, before the browser is
 * sent back to the avatar section.
 */
final class AvatarDeletionStep implements EventInterface {
	public const SELECTED = 'selected';

	public const DELETED = 'deleted';

	public function __construct(private readonly string $step, private readonly ProfileUserInterface $user) {
		if (!in_array($step, array(self::SELECTED, self::DELETED), true))
			throw new InvalidArgumentException(sprintf('Deleting an avatar has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function user(): ProfileUserInterface {
		return $this->user;
	}
}
