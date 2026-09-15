<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of marking what was posted read, on the whole board or in one forum:
 * the request checked, and everything marked, before the browser is sent on.
 */
final class MarkingReadStep implements EventInterface {
	public const SELECTED = 'selected';

	public const MARKED = 'marked';

	private const STEPS = array(self::SELECTED, self::MARKED);

	/**
	 * @param ?int $forumId the forum marked read; null for the whole board
	 * @param string $forumName the forum's name, once it is marked
	 */
	public function __construct(private readonly string $step, private readonly ?int $forumId = null, private readonly string $forumName = '') {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Marking read has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function forumId(): ?int {
		return $this->forumId;
	}

	public function forumName(): string {
		return $this->forumName;
	}
}
