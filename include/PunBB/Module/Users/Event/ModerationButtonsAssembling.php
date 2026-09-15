<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * The buttons below a search's users, named ('ban', 'delete', 'change_group'),
 * before they are placed; there are none when nothing was found or the visitor
 * may not change users. Markup appended goes before them.
 */
final class ModerationButtonsAssembling implements EventInterface {
	use MarkupEntries;

	private string $markup = '';

	/** @param array<string, string> $buttons */
	public function __construct(private readonly string $search, private readonly int $count, array $buttons) {
		if (!in_array($search, array(SearchSelected::SHOW_USERS, SearchSelected::FIND_USER), true))
			throw new InvalidArgumentException(sprintf('The users page has no search "%s" with buttons', $search));

		$this->entries = $buttons;
	}

	/** SearchSelected::SHOW_USERS or FIND_USER. */
	public function search(): string {
		return $this->search;
	}

	/** How many users the search found. */
	public function count(): int {
		return $this->count;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}

	private function accept(string $name): void {}
}
