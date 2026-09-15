<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * The header cells of the members table, named by column, before they are
 * placed; markup appended goes before the table.
 */
final class MemberTableAssembling implements EventInterface {
	use MarkupEntries;

	private string $markup = '';

	/** @param array<string, string> $cells */
	public function __construct(array $cells) {
		$this->entries = $cells;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}

	private function accept(string $name): void {}
}
