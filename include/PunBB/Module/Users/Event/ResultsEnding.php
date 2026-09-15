<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A search's results are placed; markup appended goes after them.
 */
final class ResultsEnding implements EventInterface {
	private string $markup = '';

	public function __construct(private readonly string $search, private readonly int $count) {
		if (!in_array($search, SearchSelected::SEARCHES, true))
			throw new InvalidArgumentException(sprintf('The users page has no search "%s"', $search));
	}

	/** One of SearchSelected::SEARCHES. */
	public function search(): string {
		return $this->search;
	}

	public function count(): int {
		return $this->count;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
