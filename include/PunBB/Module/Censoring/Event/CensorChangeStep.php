<?php

declare(strict_types=1);

namespace PunBB\Module\Censoring\Event;

use InvalidArgumentException;
use PunBB\Module\Censoring\Api\Data\CensorInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of changing a censored word: a word added, updated or removed, once
 * the form is submitted and valid, and once it is stored, before the browser
 * is sent back to the list.
 */
final class CensorChangeStep implements EventInterface {
	public const ADDING = 'adding';

	public const ADDED = 'added';

	public const UPDATING = 'updating';

	public const UPDATED = 'updated';

	/** A removal carries the word's id only. */
	public const REMOVING = 'removing';

	public const REMOVED = 'removed';

	private const STEPS = array(self::ADDING, self::ADDED, self::UPDATING, self::UPDATED, self::REMOVING, self::REMOVED);

	public function __construct(private readonly string $step, private readonly CensorInterface $censor) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Changing a censored word has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function censor(): CensorInterface {
		return $this->censor;
	}
}
