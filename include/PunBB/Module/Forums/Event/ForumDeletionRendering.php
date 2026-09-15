<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Event;

use InvalidArgumentException;
use PunBB\Module\Forums\Api\Data\ForumInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in the confirmation of a forum's deletion, which an observer may
 * add markup at: before it and after it.
 */
final class ForumDeletionRendering implements EventInterface {
	public const OUTPUT_START = 'output_start';

	public const END = 'end';

	private string $markup = '';

	public function __construct(private readonly string $position, private readonly ForumInterface $forum) {
		if (!in_array($position, array(self::OUTPUT_START, self::END), true))
			throw new InvalidArgumentException(sprintf('The confirmation of a forum\'s deletion has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	/** The forum's id and name. */
	public function forum(): ForumInterface {
		return $this->forum;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
