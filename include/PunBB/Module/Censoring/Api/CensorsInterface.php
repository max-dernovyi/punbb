<?php

declare(strict_types=1);

namespace PunBB\Module\Censoring\Api;

use PunBB\Module\Censoring\Api\Data\CensorInterface;

/**
 * The words the board censors.
 */
interface CensorsInterface {
	/** @return list<CensorInterface> every censored word, ordered by the word */
	public function all(): array;

	/** Stores each of $censors as a new censored word; their ids are not read. */
	public function add(CensorInterface ...$censors): void;

	/** Stores each of $censors over the censored word of its id. */
	public function update(CensorInterface ...$censors): void;

	/** Removes the censored words $ids. */
	public function remove(int ...$ids): void;
}
