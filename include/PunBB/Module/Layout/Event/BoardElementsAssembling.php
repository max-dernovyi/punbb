<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The regions naming the board and the page, before they are placed.
 */
final class BoardElementsAssembling implements EventInterface {
	use RegionsAssembling;

	public const REGIONS = array('page', 'skip', 'title', 'desc', 'navlinks', 'announcement', 'messages', 'maint');

	/** @param array<string, string> $regions */
	public function __construct(array $regions) {
		$this->carry($regions);
	}
}
