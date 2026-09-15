<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;

/**
 * The head of a search's results, before anything of them is placed: the
 * header cells of the table, named by column, and the options above and below
 * it, each joined with spaces. Markup appended goes before the results.
 */
final class ResultsTableAssembling implements EventInterface {
	use PartsByName;

	public const HEADER = 'header';

	/** Above the results: selecting every user found. */
	public const HEAD_OPTIONS = 'head_options';

	public const FOOT_OPTIONS = 'foot_options';

	private string $markup = '';

	/** @param int $count how many addresses or users the search found */
	public function __construct(private readonly string $search, private readonly int $count, Parts $header, Parts $headOptions, Parts $footOptions) {
		if (!in_array($search, SearchSelected::SEARCHES, true))
			throw new InvalidArgumentException(sprintf('The users page has no search "%s"', $search));

		$this->parts = array(self::HEADER => $header, self::HEAD_OPTIONS => $headOptions, self::FOOT_OPTIONS => $footOptions);
	}

	/** One of SearchSelected::SEARCHES. */
	public function search(): string {
		return $this->search;
	}

	public function count(): int {
		return $this->count;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
