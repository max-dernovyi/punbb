<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The whole page, rendered and not yet sent: an observer may replace it.
 */
final class PageRendered implements EventInterface {
	public function __construct(private string $html) {}

	public function html(): string {
		return $this->html;
	}

	public function replace(string $html): void {
		$this->html = $html;
	}
}
