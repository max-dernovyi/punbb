<?php

declare(strict_types=1);

namespace PunBB\Module\Prune\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of a confirmed prune: the confirmation submitted, before anything is
 * pruned, and the topics pruned, before the browser is sent back to the form.
 */
final class PruningStep implements EventInterface {
	public const SUBMITTED = 'submitted';

	public const PRUNED = 'pruned';

	private const STEPS = array(self::SUBMITTED, self::PRUNED);

	/**
	 * @param string $from 'all', or the id of the forum pruned
	 * @param ?int $lastPostBefore the moment pruned topics were last posted in before; null for every topic
	 */
	public function __construct(
		private readonly string $step,
		private readonly string $from,
		private readonly int $days,
		private readonly bool $sticky,
		private readonly ?int $lastPostBefore
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('A prune has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function from(): string {
		return $this->from;
	}

	public function days(): int {
		return $this->days;
	}

	public function sticky(): bool {
		return $this->sticky;
	}

	public function lastPostBefore(): ?int {
		return $this->lastPostBefore;
	}
}
