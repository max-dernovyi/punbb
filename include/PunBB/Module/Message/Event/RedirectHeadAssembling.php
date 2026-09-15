<?php

declare(strict_types=1);

namespace PunBB\Module\Message\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * The entries of a redirect page's head, before the stylesheets are added:
 * the refresh, the title and the theme's lines. Each entry is markup.
 */
final class RedirectHeadAssembling implements EventInterface {
	use MarkupEntries;

	/** @param array<string, string> $entries */
	public function __construct(array $entries) {
		$this->entries = $entries;
	}

	private function accept(string $name): void {}
}
