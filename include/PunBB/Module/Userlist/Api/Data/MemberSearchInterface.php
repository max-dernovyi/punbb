<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Api\Data;

/**
 * Which members a listing shows, and in what order.
 */
interface MemberSearchInterface {
	/** Usernames matching this, * standing for any run of characters; '' for every username. */
	public function username(): string;

	/** The members of this group; -1 for every group. */
	public function groupId(): int;

	/** 'username', 'registered' or 'num_posts'. */
	public function sortBy(): string;

	public function descending(): bool;
}
