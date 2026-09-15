<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Visitor;

/**
 * Who the page is served to: a signed-in user, or the guest account.
 */
interface VisitorInterface {
	public function id(): int;

	public function username(): string;

	public function groupId(): int;

	public function isGuest(): bool;

	public function isAdministrator(): bool;

	/** An administrator, or a moderator of any forum. */
	public function isModerating(): bool;

	public function can(GroupPermission $permission): bool;

	/** The name of the visitor's language pack. */
	public function language(): string;

	public function style(): string;

	/** When the visitor's previous visit ended; 0 for the guest. */
	public function lastVisit(): int;

	/** The topics and forums the visitor read since their last visit. */
	public function trackedTopics(): TrackedTopics;

	/** The page the visitor came from, '' when it is not known. */
	public function previousUrl(): string;

	/** How many topics a page of a forum shows the visitor. */
	public function topicsPerPage(): int;

	/** How many posts a page of a topic shows the visitor. */
	public function postsPerPage(): int;

	/** Whether the visitor has posters' avatars shown. */
	public function showsAvatars(): bool;

	/** Whether the visitor has posters' signatures shown. */
	public function showsSignatures(): bool;

	/** Whether the visitor subscribes to every topic they post in. */
	public function subscribesOnReply(): bool;

	/** Records that the visitor read topic $topicId at $at, among the topics they read since their last visit. */
	public function readTopic(int $topicId, int $at): void;

	/** Records that the visitor marked forum $forumId read at $at: nothing posted there before it is new to them. */
	public function readForum(int $forumId, int $at): void;

	/** Forgets every topic and forum the visitor read, as signing out does. */
	public function forgetTrackedTopics(): void;

	/** The address the request came from. */
	public function address(): string;

	/** When the visitor's current visit started; null when the board has not recorded it. */
	public function loggedAt(): ?int;

	/** The member's email address; '' for the guest. */
	public function email(): string;

	/** When the visitor last posted; null when they never did. */
	public function lastPostAt(): ?int;

	/** How many seconds the visitor's group must wait between two posts. */
	public function postFloodInterval(): int;

	/** When the visitor last sent mail or a report through the board; null when they never did. */
	public function lastEmailSentAt(): ?int;

	/** How many seconds the visitor's group must wait between two mails or reports. */
	public function emailFloodInterval(): int;

	/** When the visitor last searched; null when they never did. */
	public function lastSearchAt(): ?int;

	/** How many seconds the visitor's group must wait between two searches. */
	public function searchFloodInterval(): int;
}
