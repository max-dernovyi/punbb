<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Api\Data;

/**
 * A topic as its forum lists it.
 */
interface ListedTopicInterface {
	public function id(): int;

	public function poster(): string;

	public function subject(): string;

	public function posted(): int;

	public function firstPostId(): int;

	public function lastPost(): int;

	public function lastPostId(): int;

	public function lastPoster(): string;

	public function viewCount(): int;

	public function replyCount(): int;

	public function isClosed(): bool;

	public function isSticky(): bool;

	/** The topic this one was moved to, which it now only points at; null for a topic of its own. */
	public function movedTo(): ?int;

	/** Whether the member asked about posted in it. */
	public function hasPosted(): bool;
}
