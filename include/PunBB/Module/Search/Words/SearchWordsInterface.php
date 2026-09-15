<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Words;

/**
 * Which words the search index holds, and so which are worth searching for.
 */
interface SearchWordsInterface {
	/** The fewest characters a keyword search must carry, wildcards aside. */
	public function minimumLength(): int;

	/** Whether $word is long enough, short enough and not a stopword of the visitor's language. */
	public function searchable(string $word): bool;
}
