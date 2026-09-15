<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Event;

use InvalidArgumentException;
use PunBB\Module\Bans\Api\Data\BanInterface;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * A ban's block in the list, before it is placed: the lines naming what it
 * bans, each markup — username, email, ip, expire, message — and the markup
 * naming who created it. Markup appended goes before the block.
 */
final class BanAssembling implements EventInterface {
	use MarkupEntries;

	private string $markup = '';

	/**
	 * @param int $number the ban's place in the list, from 1
	 * @param array<string, string> $lines
	 */
	public function __construct(private readonly BanInterface $ban, private readonly int $number, array $lines, private string $creator) {
		$this->entries = $lines;
	}

	public function ban(): BanInterface {
		return $this->ban;
	}

	public function number(): int {
		return $this->number;
	}

	/** Who created the ban: a link to their profile, or the word for unknown. */
	public function creator(): string {
		return $this->creator;
	}

	public function setCreator(string $markup): void {
		$this->creator = $markup;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}

	private function accept(string $name): void {
		if ($name === '')
			throw new InvalidArgumentException('A ban\'s line needs a name');
	}
}
