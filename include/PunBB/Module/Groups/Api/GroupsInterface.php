<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Api;

use PunBB\Module\Groups\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Groups\Api\Data\GroupInterface;
use PunBB\Module\Groups\Api\Data\GroupMembersInterface;
use PunBB\Module\Groups\Api\Data\ListedGroupInterface;

/**
 * The user groups: what each allows its members, the permissions the forums
 * store for it, its members, and which group new users join.
 */
interface GroupsInterface {
	/** @return list<ListedGroupInterface> every group, by title */
	public function all(): array;

	/** @return list<ListedGroupInterface> every group a new group can be based on, by title: all but the administrators and the guests */
	public function baseGroups(): array;

	/** @return list<ListedGroupInterface> every group new users can join, by title: the members' groups that do not moderate */
	public function defaultCandidates(): array;

	/** @return list<ListedGroupInterface> every group a removed group's members can move to, by title: all but the guests and group $id */
	public function moveTargets(int $id): array;

	/** Group $id, which a new group is based on; null when there is none. */
	public function baseGroup(int $id): ?GroupInterface;

	/** Group $id, to edit; null when there is none. */
	public function group(int $id): ?GroupInterface;

	/** Whether a group other than $exceptId has title $title. */
	public function titleTaken(string $title, ?int $exceptId): bool;

	/** Stores each of $groups as a new group. */
	public function add(GroupInterface ...$groups): void;

	/** The id of the group added last. */
	public function lastAddedId(): int;

	/** Stores the titles, permissions and flood intervals of each of $groups over the group of its id. */
	public function update(GroupInterface ...$groups): void;

	/** @return list<ForumPermissionsInterface> the permissions the forums store for group $groupId */
	public function forumPermissions(int $groupId): array;

	/** Stores each of $permissions as group $groupId's in its forum. */
	public function addForumPermissions(int $groupId, ForumPermissionsInterface ...$permissions): void;

	/** Whether group $id exists and does not moderate, so new users may join it. */
	public function isDefaultCandidate(int $id): bool;

	/** Stores each of $ids in turn as the group new users join. */
	public function makeDefault(int ...$ids): void;

	/** Group $id's title and member count; null when it has no members, or is no group. */
	public function members(int $id): ?GroupMembersInterface;

	/** Moves every member of each of the groups $fromIds to group $toId. */
	public function moveMembers(int $toId, int ...$fromIds): void;

	/** Removes the groups $ids. */
	public function remove(int ...$ids): void;

	/** Removes the permissions every forum stores for the groups $groupIds. */
	public function removeForumPermissions(int ...$groupIds): void;
}
