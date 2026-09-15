<?php

declare(strict_types=1);

namespace PunBB\Module\Reindex\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of a rebuild cycle: the cycle starting, once its link is verified,
 * and the cycle's batch indexed, before the page sending the browser on.
 * Markup appended goes before the page at the start, and after the posts
 * indexed at the end.
 */
final class ReindexCycleStep implements EventInterface {
	/** Nothing was emptied or indexed yet. */
	public const START = 'start';

	/** The batch is indexed; the next cycle, if any, is known. */
	public const END = 'end';

	private const STEPS = array(self::START, self::END);

	private string $markup = '';

	/**
	 * @param bool $emptying whether the cycle empties the index before it indexes
	 * @param int $lastPostId the last post indexed; 0 at the start, or when the batch was empty
	 * @param ?int $nextPostId the post the next cycle starts at; null at the start, or when the rebuild is done
	 */
	public function __construct(
		private readonly string $step,
		private readonly int $perCycle,
		private readonly int $startAt,
		private readonly bool $emptying,
		private readonly int $lastPostId = 0,
		private readonly ?int $nextPostId = null
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('A rebuild cycle has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function perCycle(): int {
		return $this->perCycle;
	}

	public function startAt(): int {
		return $this->startAt;
	}

	public function emptying(): bool {
		return $this->emptying;
	}

	public function lastPostId(): int {
		return $this->lastPostId;
	}

	public function nextPostId(): ?int {
		return $this->nextPostId;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
