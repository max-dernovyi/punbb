<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;
use PunBB\Module\Users\Api\Data\AddressUseInterface;
use PunBB\Module\Users\Api\Data\FoundUserInterface;
use PunBB\Module\Users\Api\Data\PosterInterface;

/**
 * A stage of a row in a search's results: before its cells are built, and
 * once they are, named by column, before the row is placed; or the same two
 * stages of the row saying nothing was found. A row of addresses carries its
 * address, a row of the users who posted from an address its poster and the
 * user when the account is there, a row of the users found the user. Markup
 * appended goes before the row.
 */
final class ResultRowAssembling implements EventInterface {
	use MarkupEntries;

	public const START = 'start';

	public const CELLS = 'cells';

	/** The row saying nothing was found, before its cells are built. */
	public const EMPTY_START = 'empty_start';

	public const EMPTY_CELLS = 'empty_cells';

	private const STAGES = array(self::START, self::CELLS, self::EMPTY_START, self::EMPTY_CELLS);

	private string $markup = '';

	/**
	 * @param int $number the row's place in the table, from 1; 0 for the row saying nothing was found
	 * @param string $style the row's classes
	 * @param array<string, string> $cells
	 */
	public function __construct(
		private readonly string $search,
		private readonly string $stage,
		private readonly int $number,
		private string $style,
		array $cells,
		private readonly ?AddressUseInterface $address = null,
		private readonly ?PosterInterface $poster = null,
		private readonly ?FoundUserInterface $user = null
	) {
		if (!in_array($search, SearchSelected::SEARCHES, true))
			throw new InvalidArgumentException(sprintf('The users page has no search "%s"', $search));

		if (!in_array($stage, self::STAGES, true))
			throw new InvalidArgumentException(sprintf('A row of results has no stage "%s"', $stage));

		$this->entries = $cells;
	}

	/** One of SearchSelected::SEARCHES. */
	public function search(): string {
		return $this->search;
	}

	public function stage(): string {
		return $this->stage;
	}

	public function number(): int {
		return $this->number;
	}

	/** The row's classes; the row saying nothing was found has its own. */
	public function style(): string {
		return $this->style;
	}

	public function setStyle(string $style): void {
		$this->style = $style;
	}

	public function address(): ?AddressUseInterface {
		return $this->address;
	}

	public function poster(): ?PosterInterface {
		return $this->poster;
	}

	/** The user of the row; null for a guest's posts, or when the account is gone. */
	public function user(): ?FoundUserInterface {
		return $this->user;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}

	private function accept(string $name): void {
		if ($this->stage === self::START || $this->stage === self::EMPTY_START)
			throw new InvalidArgumentException('A row\'s cells are not built yet');
	}
}
