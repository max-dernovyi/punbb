<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The welcome and the visit links, before they are placed: the links are
 * named entries of markup, the welcome is markup or absent.
 */
final class VisitElementsAssembling implements EventInterface {
	use MarkupEntries;

	/** @param array<string, string> $links */
	public function __construct(private ?string $welcome, array $links) {
		$this->entries = $links;
	}

	public function welcome(): ?string {
		return $this->welcome;
	}

	/** Null leaves the welcome out of the chrome. */
	public function replaceWelcome(?string $markup): void {
		$this->welcome = $markup;
	}

	private function accept(string $name): void {}
}
