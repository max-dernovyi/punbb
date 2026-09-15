<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Api;

use PunBB\Module\Update\Api\Data\PostRangeInterface;

/**
 * The rows an update rewrites to the shape this release reads.
 */
interface BoardDataInterface {
	/**
	 * Makes the moderators' group, which 1.2 kept as group 2, group 4 with
	 * moderation rights, and moves the guests and the members down to 2 and 3,
	 * with their accounts and forum permissions.
	 */
	public function reorderGroups(): void;

	/** Gives every moderating group $permission ('g_mod_edit_users' …) as the board had it for all of them. */
	public function grantModerators(string $permission, int $value): void;

	/** Keeps the guests from mailing, and the administrators, guests and moderators from waiting between mails. */
	public function limitGroupMail(): void;

	/** Records each topic's first post, by the lowest id among its posts. */
	public function recordFirstPosts(): void;

	/** Moves the accounts 1.2 marked unverified by group 32000 into group 0. */
	public function moveUnverifiedUsers(): void;

	/** @return list<string> the ids of the hotfixes installed for another version than $version */
	public function supersededHotfixes(string $version): array;

	/** Removes extension $id and the code it attached. */
	public function removeExtension(string $id): void;

	/** Prefixes each LinkedIn address stored without a scheme with http://, which 1.4.0 let through as a script. */
	public function schemeLinkedinAddresses(): void;

	/** Records avatar type $type of $width by $height pixels on account $userId. */
	public function storeAvatar(int $userId, int $type, int $width, int $height): void;

	/** The lowest and highest post id, and how many posts there are. */
	public function postRange(): PostRangeInterface;

	/** The message, poster, subject and forum name of the first post from id $postId on, together; null when there is none. */
	public function postText(int $postId): ?string;

	/** @return list<int> */
	public function forumIds(): array;

	/** Counts forum $forumId's topics and posts, and records its last post, from its topics. */
	public function syncForum(int $forumId): void;

	public function emptySearchCache(): void;

	public function emptyOnline(): void;
}
