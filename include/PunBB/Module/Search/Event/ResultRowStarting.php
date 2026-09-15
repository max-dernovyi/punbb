<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Search\Api\Data\ResultForumInterface;
use PunBB\Module\Search\Api\Data\ResultPostInterface;
use PunBB\Module\Search\Api\Data\ResultTopicInterface;
use PunBB\Module\Search\Api\Data\ListingInterface;

/**
 * A result is about to be built, before anything about it is: the results are
 * counted, and markup appended goes before it.
 */
final class ResultRowStarting implements EventInterface {
	private string $markup = '';

	/** @param int $itemCount the results counted before this one */
	public function __construct(
		private readonly ListingInterface $listing,
		private readonly ResultPostInterface|ResultTopicInterface|ResultForumInterface $result,
		private int $itemCount
	) {}

	public function listing(): ListingInterface {
		return $this->listing;
	}

	public function result(): ResultPostInterface|ResultTopicInterface|ResultForumInterface {
		return $this->result;
	}

	public function itemCount(): int {
		return $this->itemCount;
	}

	public function count(int $items): void {
		$this->itemCount = $items;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
