<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Removal;

/**
 * Takes a user off the board: the account, their subscriptions, their place
 * on the moderators' lists and the online list, and their posts or the name
 * on them. The users and profile pages remove users.
 */
interface UserRemovalInterface {
	/** Removes user $userId, with every post they wrote when $withPosts, or leaving the posts as a guest's. */
	public function remove(int $userId, bool $withPosts): void;
}
