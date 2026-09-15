<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Api\Data;

/**
 * A topic found by a search, with its forum.
 */
interface ResultTopicInterface {
	public function id(): int;

	public function poster(): string;

	public function subject(): string;

	public function firstPostId(): int;

	public function posted(): int;

	public function lastPost(): int;

	public function lastPostId(): int;

	public function lastPoster(): string;

	public function replyCount(): int;

	public function isClosed(): bool;

	public function isSticky(): bool;

	public function forumId(): int;

	public function forumName(): string;

	/** Whether the member asked about posted in it. */
	public function hasPosted(): bool;
}
