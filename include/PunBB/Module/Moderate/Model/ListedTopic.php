<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Model;

use PunBB\Module\Moderate\Api\Data\ListedTopicInterface;

final readonly class ListedTopic implements ListedTopicInterface {
	public function __construct(
		private int $id,
		private string $poster,
		private string $subject,
		private int $posted,
		private int $lastPost,
		private int $lastPostId,
		private string $lastPoster,
		private int $viewCount,
		private int $replyCount,
		private bool $isClosed,
		private bool $isSticky,
		private ?int $movedTo,
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

	public function viewCount(): int {
		return $this->viewCount;
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

	public function movedTo(): ?int {
		return $this->movedTo;
	}

	public function hasPosted(): bool {
		return $this->hasPosted;
	}
}
