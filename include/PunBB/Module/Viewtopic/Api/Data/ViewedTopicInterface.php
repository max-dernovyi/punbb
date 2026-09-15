<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Api\Data;

/**
 * A topic as its page shows it, with its forum.
 */
interface ViewedTopicInterface {
	public function id(): int;

	public function subject(): string;

	public function firstPostId(): int;

	public function isClosed(): bool;

	public function isSticky(): bool;

	public function replyCount(): int;

	public function forumId(): int;

	public function forumName(): string;

	/** @return list<ModeratorInterface> the forum's moderators */
	public function moderators(): array;

	/** Whether the visitor's group may reply in the forum; null when the forum leaves it to the group. */
	public function groupPostsReplies(): ?bool;

	/** Whether the visitor is subscribed to it; false when that was not asked. */
	public function isSubscribed(): bool;
}
