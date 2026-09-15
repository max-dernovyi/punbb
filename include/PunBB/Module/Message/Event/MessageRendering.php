<?php

declare(strict_types=1);

namespace PunBB\Module\Message\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in a message's markup, which an observer may add markup at.
 */
final class MessageRendering implements EventInterface {
	public const START = 'start';

	public const END = 'end';

	private string $markup = '';

	public function __construct(private readonly string $position) {
		if (!in_array($position, array(self::START, self::END), true))
			throw new InvalidArgumentException(sprintf('A message has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
