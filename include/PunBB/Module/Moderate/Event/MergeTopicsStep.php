<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of merging topics into the oldest of them: once confirmed, before
 * they are checked; and once merged, before the browser is sent back to the forum.
 */
final class MergeTopicsStep implements EventInterface {
	public const CONFIRMED = 'confirmed';

	/** Carries the topic merged into and whether redirects were left. */
	public const MERGED = 'merged';

	/** @param list<int> $topicIds */
	public function __construct(private readonly string $step, private readonly array $topicIds, private readonly int $mergedInto = 0, private readonly bool $leftRedirects = false) {
		if (!in_array($step, array(self::CONFIRMED, self::MERGED), true))
			throw new InvalidArgumentException(sprintf('Merging topics has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** @return list<int> */
	public function topicIds(): array {
		return $this->topicIds;
	}

	public function mergedInto(): int {
		return $this->mergedInto;
	}

	public function leftRedirects(): bool {
		return $this->leftRedirects;
	}
}
