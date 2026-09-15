<?php

declare(strict_types=1);

namespace PunBB\Module\Ranks\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Ranks\Api\Data\RankInterface;

/**
 * A step of changing a rank: a rank added, updated or removed, once the form
 * is submitted and valid, and once it is stored, before the browser is sent
 * back to the list.
 */
final class RankChangeStep implements EventInterface {
	public const ADDING = 'adding';

	public const ADDED = 'added';

	public const UPDATING = 'updating';

	public const UPDATED = 'updated';

	/** A removal carries the rank's id only. */
	public const REMOVING = 'removing';

	public const REMOVED = 'removed';

	private const STEPS = array(self::ADDING, self::ADDED, self::UPDATING, self::UPDATED, self::REMOVING, self::REMOVED);

	public function __construct(private readonly string $step, private readonly RankInterface $rank) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Changing a rank has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function rank(): RankInterface {
		return $this->rank;
	}
}
