<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Search\Api\Data\ResultForumInterface;
use PunBB\Module\Search\Api\Data\ResultPostInterface;
use PunBB\Module\Search\Api\Data\ResultTopicInterface;
use PunBB\Module\Search\Api\Data\ListingInterface;
use PunBB\Module\Search\View\Paging;

/**
 * A step of answering a search: what to list is known, or the form is to be
 * shown, before the results are read; the results are about to be read; they
 * are read and paged, when there are any; and they are read, before the
 * page is built.
 */
final class SearchStep implements EventInterface {
	/** What to list is known; null when the search form is shown instead. */
	public const QUERYING = 'querying';

	public const FETCHING = 'fetching';

	/** Reached only when there are results. */
	public const PAGINATED = 'paginated';

	public const FETCHED = 'fetched';

	private const STEPS = array(self::QUERYING, self::FETCHING, self::PAGINATED, self::FETCHED);

	/**
	 * @param int $hits how many results there are; 0 before they are read
	 * @param ?Paging $paging which page is shown, once there are results
	 * @param list<ResultPostInterface|ResultTopicInterface|ResultForumInterface> $results the results on the page
	 */
	public function __construct(
		private readonly string $step,
		private readonly ?ListingInterface $listing,
		private readonly int $hits = 0,
		private readonly ?Paging $paging = null,
		private readonly array $results = array()
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Answering a search has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** What is listed; null when the form is shown. */
	public function listing(): ?ListingInterface {
		return $this->listing;
	}

	public function hits(): int {
		return $this->hits;
	}

	/** The page shown, from 1; 0 before the results are read. */
	public function page(): int {
		return $this->paging !== null ? $this->paging->page : 0;
	}

	public function pageCount(): int {
		return $this->paging !== null ? $this->paging->pages : 0;
	}

	/** How many results a page shows; 0 when one page shows every result. */
	public function perPage(): int {
		return $this->paging !== null ? $this->paging->perPage : 0;
	}

	/** How many results come before the page. */
	public function offset(): int {
		return $this->paging !== null ? $this->paging->offset : 0;
	}

	/** How many results come up to the page's last. */
	public function last(): int {
		return $this->paging !== null ? $this->paging->last : 0;
	}

	/** Whether the results are read and paged. */
	public function paged(): bool {
		return $this->paging !== null;
	}

	/** @return list<ResultPostInterface> the posts on the page */
	public function posts(): array {
		return array_values(array_filter($this->results, static fn (ResultPostInterface|ResultTopicInterface|ResultForumInterface $result): bool => $result instanceof ResultPostInterface));
	}

	/** @return list<ResultTopicInterface> the topics on the page */
	public function topics(): array {
		return array_values(array_filter($this->results, static fn (ResultPostInterface|ResultTopicInterface|ResultForumInterface $result): bool => $result instanceof ResultTopicInterface));
	}

	/** @return list<ResultForumInterface> the forums on the page */
	public function forums(): array {
		return array_values(array_filter($this->results, static fn (ResultPostInterface|ResultTopicInterface|ResultForumInterface $result): bool => $result instanceof ResultForumInterface));
	}
}
