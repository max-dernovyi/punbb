<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Controller;

use PunBB\Module\Layout\View\Html;

/**
 * What a stage of the update did, and the query of the stage after it.
 */
final readonly class StageResult {
	/**
	 * @param list<Html> $lines
	 * @param string $next the query naming the next stage, '' when the update does not go on from here
	 */
	public function __construct(public array $lines = array(), public string $next = '') {}
}
