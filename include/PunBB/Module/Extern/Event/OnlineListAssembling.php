<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * Who is online, before it is written: how many guests and members, and the
 * members, each a name or a link to their profile.
 */
final class OnlineListAssembling implements EventInterface {
	use MarkupEntries;

	/** @param array<string, string> $members */
	public function __construct(private int $guests, private int $memberCount, array $members, private readonly bool $full) {
		$this->entries = $members;
	}

	public function guests(): int {
		return $this->guests;
	}

	public function memberCount(): int {
		return $this->memberCount;
	}

	public function setCounts(int $guests, int $members): void {
		$this->guests = $guests;
		$this->memberCount = $members;
	}

	/** Whether the members are listed by name, not only counted. */
	public function isFull(): bool {
		return $this->full;
	}

	private function accept(string $name): void {}
}
