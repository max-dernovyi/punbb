<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Search\Api\Data\ListingInterface;

/**
 * A position on the results page, which an observer may add markup at: before
 * the results, where the links above and below them may still change, and
 * after them.
 */
final class ResultsRendering implements EventInterface {
	use PartsByName;

	public const START = 'start';

	public const END = 'end';

	public const HEAD_OPTIONS = ResultsHeadAssembling::HEAD_OPTIONS;

	public const FOOT_OPTIONS = ResultsHeadAssembling::FOOT_OPTIONS;

	private string $markup = '';

	/** @param string $itemsInfo markup: "Topics found: 1 to 3 of 6" */
	public function __construct(
		private readonly string $position,
		private readonly ListingInterface $listing,
		private readonly string $itemsInfo,
		Parts $headOptions,
		Parts $footOptions
	) {
		if (!in_array($position, array(self::START, self::END), true))
			throw new InvalidArgumentException(sprintf('The results page has no position "%s"', $position));

		$this->parts = array(self::HEAD_OPTIONS => $headOptions, self::FOOT_OPTIONS => $footOptions);
	}

	public function position(): string {
		return $this->position;
	}

	public function listing(): ListingInterface {
		return $this->listing;
	}

	public function itemsInfo(): string {
		return $this->itemsInfo;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
