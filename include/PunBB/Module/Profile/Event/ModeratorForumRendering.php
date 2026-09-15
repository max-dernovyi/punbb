<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\FormMarkup;
use PunBB\Module\Profile\Api\Data\ModeratableForumInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * A forum of the list choosing the forums a member moderates, before its
 * checkbox and its category's heading, or after the checkbox: an observer may
 * add markup there. The list numbers its fields on from the form.
 */
final class ModeratorForumRendering implements EventInterface {
	use FormMarkup;

	public const START = 'start';

	public const END = 'end';

	public function __construct(
		private readonly string $position,
		private readonly ProfileUserInterface $user,
		private readonly ModeratableForumInterface $forum,
		int $groupCount,
		int $itemCount,
		int $fieldCount
	) {
		if (!in_array($position, array(self::START, self::END), true))
			throw new InvalidArgumentException(sprintf('A forum of the moderators\' list has no position "%s"', $position));

		$this->count($groupCount, $itemCount, $fieldCount);
	}

	public function position(): string {
		return $this->position;
	}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	public function forum(): ModeratableForumInterface {
		return $this->forum;
	}
}
