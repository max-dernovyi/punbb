<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\View;

use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * Named pieces of markup that are joined into one: a row's title, its
 * classes, its lines.
 */
final class Parts {
	use MarkupEntries;

	/** @param array<string, string> $parts */
	public function __construct(array $parts = array()) {
		$this->entries = $parts;
	}

	public function isEmpty(): bool {
		return $this->entries === array();
	}

	public function join(string $glue): string {
		return implode($glue, $this->entries);
	}

	private function accept(string $name): void {}
}
