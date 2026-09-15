<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Api\Data;

/**
 * What a search for users asks for: every criterion given must hold. The guest
 * account is never found.
 */
interface UserSearchInterface {
	/** The columns the results can be ordered by. */
	public const ORDERS = array('username', 'email', 'num_posts', 'last_post', 'registered');

	/** @return list<SearchFieldInterface> the columns matched, in the order the search named them */
	public function fields(): array;

	/** Found users have more posts than this; null for any count. */
	public function postsMoreThan(): ?int;

	public function postsLessThan(): ?int;

	/** Found users last posted after this moment; null for any. */
	public function lastPostAfter(): ?int;

	public function lastPostBefore(): ?int;

	public function registeredAfter(): ?int;

	public function registeredBefore(): ?int;

	/** The group found users are in; -1 for any. */
	public function groupId(): int;

	/** One of ORDERS. */
	public function orderBy(): string;

	public function descending(): bool;
}
