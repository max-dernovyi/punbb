<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Chrome\ChromeException;

/**
 * A position inside the debug footer, which an observer may add markup at.
 */
final class DebugRendering implements EventInterface {
	public const START = 'start';

	public const END = 'end';

	private string $markup = '';

	public function __construct(private readonly string $position) {
		if (!in_array($position, array(self::START, self::END), true))
			throw new ChromeException(sprintf('The debug footer has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	/** What the observers added, in the order they added it. */
	public function markup(): string {
		return $this->markup;
	}
}
