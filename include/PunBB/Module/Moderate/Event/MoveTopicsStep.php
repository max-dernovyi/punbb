<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of moving topics to another forum: once confirmed, before the topics
 * and the forum are read; and once moved, before the browser is sent there.
 */
final class MoveTopicsStep implements EventInterface {
	public const CONFIRMED = 'confirmed';

	/** Carries the topics, the forum they moved to and whether redirects were left. */
	public const MOVED = 'moved';

	/** @param list<int> $topicIds */
	public function __construct(
		private readonly string $step,
		private readonly array $topicIds = array(),
		private readonly int $forumId = 0,
		private readonly string $forumName = '',
		private readonly bool $leftRedirects = false
	) {
		if (!in_array($step, array(self::CONFIRMED, self::MOVED), true))
			throw new InvalidArgumentException(sprintf('Moving topics has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** @return list<int> */
	public function topicIds(): array {
		return $this->topicIds;
	}

	public function forumId(): int {
		return $this->forumId;
	}

	public function forumName(): string {
		return $this->forumName;
	}

	public function leftRedirects(): bool {
		return $this->leftRedirects;
	}
}
