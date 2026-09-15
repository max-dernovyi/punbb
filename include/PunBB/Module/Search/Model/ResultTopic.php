<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Model;

use PunBB\Module\Search\Api\Data\ResultTopicInterface;

final readonly class ResultTopic implements ResultTopicInterface {
	public function __construct(
		private int $id,
		private string $poster,
		private string $subject,
		private int $firstPostId,
		private int $posted,
		private int $lastPost,
		private int $lastPostId,
		private string $lastPoster,
		private int $replyCount,
		private bool $isClosed,
		private bool $isSticky,
		private int $forumId,
		private string $forumName,
		private bool $hasPosted
	) {}

	public function id(): int {
		return $this->id;
	}

	public function poster(): string {
		return $this->poster;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function firstPostId(): int {
		return $this->firstPostId;
	}

	public function posted(): int {
		return $this->posted;
	}

	public function lastPost(): int {
		return $this->lastPost;
	}

	public function lastPostId(): int {
		return $this->lastPostId;
	}

	public function lastPoster(): string {
		return $this->lastPoster;
	}

	public function replyCount(): int {
		return $this->replyCount;
	}

	public function isClosed(): bool {
		return $this->isClosed;
	}

	public function isSticky(): bool {
		return $this->isSticky;
	}

	public function forumId(): int {
		return $this->forumId;
	}

	public function forumName(): string {
		return $this->forumName;
	}

	public function hasPosted(): bool {
		return $this->hasPosted;
	}
}
