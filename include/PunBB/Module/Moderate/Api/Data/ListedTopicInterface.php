<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Api\Data;

/**
 * A topic in the list of a forum being moderated.
 */
interface ListedTopicInterface {
	public function id(): int;

	public function poster(): string;

	public function subject(): string;

	public function posted(): int;

	public function lastPost(): int;

	public function lastPostId(): int;

	public function lastPoster(): string;

	public function viewCount(): int;

	public function replyCount(): int;

	public function isClosed(): bool;

	public function isSticky(): bool;

	/** The topic a redirect points at; null for a topic of the forum. */
	public function movedTo(): ?int;

	/** Whether the visitor asked about posted in it. */
	public function hasPosted(): bool;
}
