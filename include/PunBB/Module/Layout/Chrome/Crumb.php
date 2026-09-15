<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

use PunBB\Module\Layout\View\Html;

/**
 * One step of the breadcrumbs: its text, and where it leads unless it is where the visitor is.
 */
final readonly class Crumb {
	public function __construct(public string $text, public ?Html $link = null) {}
}
