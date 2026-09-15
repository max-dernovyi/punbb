<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Api\Data;

/**
 * A post found by a search, with its topic and forum.
 */
interface ResultPostInterface {
	public function id(): int;

	public function poster(): string;

	public function posterId(): int;

	public function posted(): int;

	public function message(): string;

	public function hidesSmilies(): bool;

	public function topicId(): int;

	public function topicPoster(): string;

	public function subject(): string;

	public function firstPostId(): int;

	public function topicPosted(): int;

	public function lastPost(): int;

	public function lastPostId(): int;

	public function lastPoster(): string;

	public function replyCount(): int;

	public function forumId(): int;

	public function forumName(): string;
}
