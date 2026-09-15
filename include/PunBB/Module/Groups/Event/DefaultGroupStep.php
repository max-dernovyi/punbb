<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of choosing the group new users join: once the form is submitted,
 * before the group is checked, and once it is stored.
 */
final class DefaultGroupStep implements EventInterface {
	public const SETTING = 'setting';

	public const SET = 'set';

	public function __construct(private readonly string $step, private readonly int $groupId) {
		if (!in_array($step, array(self::SETTING, self::SET), true))
			throw new InvalidArgumentException(sprintf('Choosing the default group has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function groupId(): int {
		return $this->groupId;
	}
}
