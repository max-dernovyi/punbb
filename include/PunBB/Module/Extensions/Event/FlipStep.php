<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of disabling or enabling an extension: the switch asked for, once
 * its link's token checks out; and the extension switched, before the browser is sent on.
 */
final class FlipStep implements EventInterface {
	public const SELECTED = 'selected';

	public const FLIPPED = 'flipped';

	private const STEPS = array(self::SELECTED, self::FLIPPED);

	/** @param bool $disabling whether the extension is disabled; known once it is switched */
	public function __construct(private readonly string $step, private readonly string $id, private readonly bool $disabling = false) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Switching an extension has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function id(): string {
		return $this->id;
	}

	public function disabling(): bool {
		return $this->disabling;
	}
}
