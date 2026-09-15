<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Api;

use PunBB\Module\Bans\Api\Data\BanInterface;

/**
 * The bans the board keeps members and addresses out with.
 */
interface BansInterface {
	public function count(): int;

	/** @return list<BanInterface> $limit bans from the $offset-th, in the order they were created */
	public function page(int $offset, int $limit): array;

	/** Ban $id; null when there is none. */
	public function find(int $id): ?BanInterface;

	/** Stores each of $bans as a new ban; their ids are not read. */
	public function add(BanInterface ...$bans): void;

	/** Stores each of $bans over the ban of its id; who created it stays. */
	public function update(BanInterface ...$bans): void;

	/** Removes the bans $ids. */
	public function remove(int ...$ids): void;
}
