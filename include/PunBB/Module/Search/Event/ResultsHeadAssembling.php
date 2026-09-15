<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Search\Api\Data\ListingInterface;

/**
 * The results page's head is about to be built: an observer may add the links
 * above and below the results before the page adds its own.
 */
final class ResultsHeadAssembling implements EventInterface {
	use PartsByName;

	/** The links above the results, joined with spaces. */
	public const HEAD_OPTIONS = 'head_options';

	/** The links below the results, joined with spaces. */
	public const FOOT_OPTIONS = 'foot_options';

	public function __construct(private readonly ListingInterface $listing, Parts $headOptions, Parts $footOptions) {
		$this->parts = array(self::HEAD_OPTIONS => $headOptions, self::FOOT_OPTIONS => $footOptions);
	}

	public function listing(): ListingInterface {
		return $this->listing;
	}
}
