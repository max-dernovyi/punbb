<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Model;

use PunBB\Module\Misc\Api\Data\NewReportInterface;

final readonly class NewReport implements NewReportInterface {
	public function __construct(
		private int $postId,
		private int $topicId,
		private int $forumId,
		private int $reporterId,
		private int $createdAt,
		private string $message
	) {}

	public function postId(): int {
		return $this->postId;
	}

	public function topicId(): int {
		return $this->topicId;
	}

	public function forumId(): int {
		return $this->forumId;
	}

	public function reporterId(): int {
		return $this->reporterId;
	}

	public function createdAt(): int {
		return $this->createdAt;
	}

	public function message(): string {
		return $this->message;
	}
}
