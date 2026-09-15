<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

/**
 * A script of the page's own that goes out with the board's: a URL, or inline code.
 */
final readonly class PageScript {
	public function __construct(public string $code, public bool $inline = false) {}
}
