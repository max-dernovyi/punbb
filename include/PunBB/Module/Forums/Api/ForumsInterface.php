<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Api;

use PunBB\Module\Forums\Api\Data\CategoryInterface;
use PunBB\Module\Forums\Api\Data\ForumInterface;
use PunBB\Module\Forums\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Forums\Api\Data\ForumPositionInterface;
use PunBB\Module\Forums\Api\Data\GroupDefaultsInterface;
use PunBB\Module\Forums\Api\Data\GroupPermissionsInterface;
use PunBB\Module\Forums\Api\Data\ListedForumInterface;

/**
 * The board's forums, their places in the categories, and what each group may
 * do in them where that differs from the group's own permissions.
 */
interface ForumsInterface {
	/** @return list<ListedForumInterface> every forum with its category, by the category's position and the forum's */
	public function all(): array;

	/** @return list<CategoryInterface> every category by position, for the form adding a forum */
	public function categories(): array;

	/** @return list<CategoryInterface> every category by position, for the form editing a forum */
	public function assignableCategories(): array;

	public function categoryExists(int $id): bool;

	/** Stores each of $forums as a new forum; their ids are not read. */
	public function add(ForumInterface ...$forums): void;

	/** Forum $id; null when there is none. */
	public function find(int $id): ?ForumInterface;

	/** The name of forum $id; null when there is none. */
	public function name(int $id): ?string;

	/** Stores the name, description, redirect, sorting and category of each of $forums over the forum of its id. */
	public function update(ForumInterface ...$forums): void;

	/** @return list<ForumPositionInterface> every forum's place, by the category's position and the forum's */
	public function positions(): array;

	/** Moves each forum to the position $positions give it. */
	public function reposition(ForumPositionInterface ...$positions): void;

	/** Removes the forums $ids, once their topics are gone. */
	public function remove(int ...$ids): void;

	/** Removes every group's permissions in the forums $forumIds, once they are gone. */
	public function removePermissions(int ...$forumIds): void;

	/** Removes every subscription to the forums $forumIds. */
	public function removeSubscriptions(int ...$forumIds): void;

	/** @return list<GroupDefaultsInterface> what every group but the administrators may do on the whole board */
	public function groupDefaults(): array;

	/** @return list<GroupPermissionsInterface> every group but the administrators, by id, with its permissions in forum $forumId */
	public function groupPermissions(int $forumId): array;

	/** Whether forum $forumId stores permissions of its own for group $groupId. */
	public function permissionsStored(int $forumId, int $groupId): bool;

	/** Stores each of $permissions over the forum $forumId's permissions for its group. */
	public function updatePermissions(int $forumId, ForumPermissionsInterface ...$permissions): void;

	/** Stores each of $permissions as the forum $forumId's permissions for its group. */
	public function addPermissions(int $forumId, ForumPermissionsInterface ...$permissions): void;

	/** Removes the forum $forumId's permissions for the groups $groupIds, which then do what they may on the board. */
	public function removeGroupPermissions(int $forumId, int ...$groupIds): void;

	/** Removes every permission the forums $forumIds store, when their defaults are restored. */
	public function revertPermissions(int ...$forumIds): void;
}
