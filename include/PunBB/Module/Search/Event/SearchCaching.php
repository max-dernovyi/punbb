<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Search\Api\Data\SearchCriteriaInterface;

/**
 * A step of running a keyword or author search: before anything is checked,
 * and once what it found is stored, before the searcher is sent to the
 * results. An observer that stops it at either step leaves the searcher on the
 * search form.
 */
final class SearchCaching implements EventInterface {
	public const START = 'start';

	public const STORED = 'stored';

	private bool $stopped = false;

	/** @param int $searchId the id the results are stored under; 0 before they are */
	public function __construct(private readonly string $step, private readonly SearchCriteriaInterface $criteria, private readonly int $searchId = 0) {
		if (!in_array($step, array(self::START, self::STORED), true))
			throw new InvalidArgumentException(sprintf('Running a search has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function criteria(): SearchCriteriaInterface {
		return $this->criteria;
	}

	public function searchId(): int {
		return $this->searchId;
	}

	public function stop(): void {
		$this->stopped = true;
	}

	public function stopped(): bool {
		return $this->stopped;
	}
}
