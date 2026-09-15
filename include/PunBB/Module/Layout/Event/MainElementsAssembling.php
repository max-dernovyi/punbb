<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The regions around the page's main content, before they are placed.
 */
final class MainElementsAssembling implements EventInterface {
	use RegionsAssembling;

	public const REGIONS = array('crumbs_top', 'crumbs_end', 'main_title', 'main_pagepost_top', 'main_pagepost_end', 'main_menu', 'admin_menu', 'admin_submenu');

	/** @param array<string, string> $regions */
	public function __construct(array $regions) {
		$this->carry($regions);
	}
}
