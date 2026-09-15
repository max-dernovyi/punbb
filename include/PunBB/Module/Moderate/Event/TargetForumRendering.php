<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Moderate\Api\Data\TargetForumInterface;

/**
 * A forum's option in the list of forums topics move to, where an observer may
 * add markup: before the option, and its category's group when it opens one,
 * and after it.
 */
final class TargetForumRendering implements EventInterface {
	public const START = 'start';

	public const END = 'end';

	private string $markup = '';

	public function __construct(private readonly string $position, private readonly TargetForumInterface $forum) {
		if (!in_array($position, array(self::START, self::END), true))
			throw new InvalidArgumentException(sprintf('A forum\'s option has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function forum(): TargetForumInterface {
		return $this->forum;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
