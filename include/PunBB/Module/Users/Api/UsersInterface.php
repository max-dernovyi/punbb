<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Api;

use PunBB\Module\Users\Api\Data\AddressUseInterface;
use PunBB\Module\Users\Api\Data\BanTargetInterface;
use PunBB\Module\Users\Api\Data\FoundUserInterface;
use PunBB\Module\Users\Api\Data\ListedGroupInterface;
use PunBB\Module\Users\Api\Data\PostAddressInterface;
use PunBB\Module\Users\Api\Data\PosterInterface;
use PunBB\Module\Users\Api\Data\UserBanInterface;
use PunBB\Module\Users\Api\Data\UserSearchInterface;

/**
 * The users as the administration searches them: by what they entered, by the
 * addresses they posted from, and the changes made to many at once.
 */
interface UsersInterface {
	/** @return list<AddressUseInterface> the addresses user $userId posted from, the one used last first */
	public function addressesOf(int $userId): array;

	/** @return list<PosterInterface> who posted from $address, each once, by the name the posts carry, descending */
	public function postersFrom(string $address): array;

	/** User $id with their group; null for the guest account, a user who is not, or one whose group is gone. */
	public function member(int $id): ?FoundUserInterface;

	/** How many users $search finds. */
	public function count(UserSearchInterface $search): int;

	/** @return list<FoundUserInterface> at most $limit of the users $search finds, after the first $offset, in its order */
	public function find(UserSearchInterface $search, int $offset, int $limit): array;

	/** @return list<ListedGroupInterface> every group but the guests, by title: what a search can be limited to */
	public function searchGroups(): array;

	/** Whether any of the users $ids is an administrator. */
	public function includesAdministrators(int ...$ids): bool;

	/** @return list<PostAddressInterface> the address of every post the users $ids wrote, but the guest account, the oldest first */
	public function postAddresses(int ...$ids): array;

	/** @return list<BanTargetInterface> the users $ids, but the guest account */
	public function banTargets(int ...$ids): array;

	/** Stores each of $bans. */
	public function ban(UserBanInterface ...$bans): void;

	/** Whether group $id moderates; null when there is no such group. */
	public function groupModerates(int $id): ?bool;

	/** Moves the users $ids, but the guest account, into group $groupId. */
	public function moveToGroup(int $groupId, int ...$ids): void;

	/** @return list<ListedGroupInterface> every group but the guests, by title: what users can be moved to */
	public function moveTargets(): array;
}
