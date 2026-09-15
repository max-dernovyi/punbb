<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The entries of the page's head, before it is rendered: an observer may add,
 * replace and remove them. Each entry is markup.
 */
final class HeadAssembling implements EventInterface {
	use MarkupEntries;

	/** @param array<string, string> $entries */
	public function __construct(array $entries) {
		$this->entries = $entries;
	}

	private function accept(string $name): void {}
}
