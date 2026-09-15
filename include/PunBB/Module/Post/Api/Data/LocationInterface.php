<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Api\Data;

/**
 * Where a post goes: a forum to start a topic in, or a topic of a forum to reply to.
 */
interface LocationInterface {
	public function forumId(): int;

	public function forumName(): string;

	/** @return list<ModeratorInterface> the forum's moderators */
	public function moderators(): array;

	/** Where the forum sends its visitors instead; '' for a forum of its own. */
	public function redirectUrl(): string;

	/** Whether the visitor's group may reply in the forum; null where the forum keeps to the group's own permission. */
	public function groupPostsReplies(): ?bool;

	/** Whether the visitor's group may start topics in the forum; null where the forum keeps to the group's own permission. */
	public function groupPostsTopics(): ?bool;

	/** The topic replied to; 0 for a new topic. */
	public function topicId(): int;

	/** The subject of the topic replied to; '' for a new topic. */
	public function subject(): string;

	public function topicClosed(): bool;

	/** Whether the visitor is subscribed to the topic replied to. */
	public function subscribed(): bool;
}
