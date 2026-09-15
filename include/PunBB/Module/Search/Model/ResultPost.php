<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Model;

use PunBB\Module\Search\Api\Data\ResultPostInterface;

final readonly class ResultPost implements ResultPostInterface {
	public function __construct(
		private int $id,
		private string $poster,
		private int $posterId,
		private int $posted,
		private string $message,
		private bool $hidesSmilies,
		private int $topicId,
		private string $topicPoster,
		private string $subject,
		private int $firstPostId,
		private int $topicPosted,
		private int $lastPost,
		private int $lastPostId,
		private string $lastPoster,
		private int $replyCount,
		private int $forumId,
		private string $forumName
	) {}

	public function id(): int {
		return $this->id;
	}

	public function poster(): string {
		return $this->poster;
	}

	public function posterId(): int {
		return $this->posterId;
	}

	public function posted(): int {
		return $this->posted;
	}

	public function message(): string {
		return $this->message;
	}

	public function hidesSmilies(): bool {
		return $this->hidesSmilies;
	}

	public function topicId(): int {
		return $this->topicId;
	}

	public function topicPoster(): string {
		return $this->topicPoster;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function firstPostId(): int {
		return $this->firstPostId;
	}

	public function topicPosted(): int {
		return $this->topicPosted;
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

	public function forumId(): int {
		return $this->forumId;
	}

	public function forumName(): string {
		return $this->forumName;
	}
}
