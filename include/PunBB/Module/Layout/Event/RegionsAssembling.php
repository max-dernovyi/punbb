<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Event;

use PunBB\Module\Layout\Chrome\ChromeException;

/**
 * A fixed set of chrome regions, each markup or absent: an absent region is
 * left out of the chrome.
 */
trait RegionsAssembling {
	use MarkupEntries;

	/** @param array<string, string> $regions */
	private function carry(array $regions): void {
		foreach ($regions as $name => $markup)
			$this->set($name, $markup);
	}

	private function accept(string $name): void {
		if (!in_array($name, self::REGIONS, true))
			throw new ChromeException(sprintf('%s carries the regions %s, not "%s"', self::class, implode(', ', self::REGIONS), $name));
	}
}
