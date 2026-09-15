<?php

declare(strict_types=1);

namespace PunBB\Module\Ranks\Api;

use PunBB\Module\Ranks\Api\Data\RankInterface;

/**
 * The ranks members earn by posting.
 */
interface RanksInterface {
	/** @return list<RankInterface> every rank, from the fewest posts */
	public function all(): array;

	/** Whether a rank other than rank $exceptId, when one is given, is earned at $minPosts posts. */
	public function minPostsTaken(int $minPosts, ?int $exceptId): bool;

	/** Stores each of $ranks as a new rank; their ids are not read. */
	public function add(RankInterface ...$ranks): void;

	/** Stores each of $ranks over the rank of its id. */
	public function update(RankInterface ...$ranks): void;

	/** Removes the ranks $ids. */
	public function remove(int ...$ids): void;
}
