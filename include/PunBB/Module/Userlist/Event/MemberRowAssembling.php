<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;
use PunBB\Module\Userlist\Api\Data\MemberInterface;

/**
 * A member's row, its cells named by column, before it is placed; markup
 * appended goes before the row.
 */
final class MemberRowAssembling implements EventInterface {
	use MarkupEntries;

	private string $markup = '';

	/** @param array<string, string> $cells */
	public function __construct(private readonly MemberInterface $member, private readonly int $number, array $cells) {
		$this->entries = $cells;
	}

	public function member(): MemberInterface {
		return $this->member;
	}

	/** The row's place in the table, from 1. */
	public function number(): int {
		return $this->number;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}

	private function accept(string $name): void {}
}
