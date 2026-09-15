<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Api\Data;

/**
 * A forum as its page shows it.
 */
interface ViewedForumInterface {
	public function id(): int;

	public function name(): string;

	public function description(): string;

	/** Where a forum on another site sends the visitor; '' for a forum of this board. */
	public function redirectUrl(): string;

	/** @return list<ModeratorInterface> the forum's moderators */
	public function moderators(): array;

	public function topicCount(): int;

	/** Whether its topics are listed by when they were posted, not by their last post. */
	public function sortsByPosted(): bool;

	/** Whether the visitor's group may start topics here; null when the forum leaves it to the group. */
	public function groupPostsTopics(): ?bool;

	/** Whether the visitor is subscribed to it; false when that was not asked. */
	public function isSubscribed(): bool;
}
