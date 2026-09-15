<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Api;

use PunBB\Module\Profile\Api\Data\AvatarInterface;
use PunBB\Module\Profile\Api\Data\DetailsInterface;
use PunBB\Module\Profile\Api\Data\EmailActivationInterface;
use PunBB\Module\Profile\Api\Data\EmailChangeInterface;
use PunBB\Module\Profile\Api\Data\ForumModeratorsInterface;
use PunBB\Module\Profile\Api\Data\ListedGroupInterface;
use PunBB\Module\Profile\Api\Data\ModeratableForumInterface;
use PunBB\Module\Profile\Api\Data\PasswordInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Api\Data\RenameInterface;

/**
 * The members' profiles: their accounts, and the places a member's name, group
 * and moderation are written to when their profile changes them.
 */
interface ProfilesInterface {
	/** Member $id with their group; null when there is none. */
	public function user(int $id): ?ProfileUserInterface;

	/** Stores each password, and drops the key that reset it. */
	public function resetPassword(PasswordInterface ...$passwords): void;

	/** Stores each password. */
	public function changePassword(PasswordInterface ...$passwords): void;

	/** Makes the address each of the members $userIds asked for their own, and drops the key that confirmed it. */
	public function confirmEmail(int ...$userIds): void;

	/** @return list<string> the name of every member registered with $email */
	public function usernamesWithEmail(string $email): array;

	/** Stores each address, unconfirmed. */
	public function changeEmail(EmailChangeInterface ...$changes): void;

	/** Keeps each address asked for, with the key that confirms it. */
	public function requestEmailChange(EmailActivationInterface ...$activations): void;

	/** Moves the members $userIds into group $groupId. */
	public function moveToGroup(int $groupId, int ...$userIds): void;

	/** Whether group $groupId moderates; false when there is no such group. */
	public function groupModerates(int $groupId): bool;

	/** @return list<ForumModeratorsInterface> every forum with its moderators */
	public function forumModerators(): array;

	/** Stores the moderators of each forum; a forum with none stores NULL. */
	public function storeModerators(ForumModeratorsInterface ...$forums): void;

	/** Records each member's avatar. */
	public function storeAvatar(AvatarInterface ...$avatars): void;

	/** Stores each section of a profile. */
	public function updateDetails(DetailsInterface ...$details): void;

	/** Puts each new name on the posts the member wrote. */
	public function renamePosts(RenameInterface ...$renames): void;

	/** Puts each new name on the topics the member started. */
	public function renameTopics(RenameInterface ...$renames): void;

	/** Puts each new name on the topics the member posted in last. */
	public function renameTopicLastPosters(RenameInterface ...$renames): void;

	/** Puts each new name on the forums the member posted in last. */
	public function renameForumLastPosters(RenameInterface ...$renames): void;

	/** Puts each new name on the online list. */
	public function renameOnline(RenameInterface ...$renames): void;

	/** Puts each new name on the posts the member edited. */
	public function renameEditors(RenameInterface ...$renames): void;

	/** @return list<ListedGroupInterface> every group a member may be in, by title */
	public function groups(): array;

	/** @return list<ModeratableForumInterface> every forum that is not a redirect, in the order the board lists them */
	public function moderatableForums(): array;
}
