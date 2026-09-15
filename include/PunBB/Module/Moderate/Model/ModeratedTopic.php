<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Model;

use PunBB\Module\Moderate\Api\Data\ModeratedTopicInterface;

final readonly class ModeratedTopic implements ModeratedTopicInterface {
	public function __construct(
		private int $id,
		private string $subject,
		private string $poster,
		private int $firstPostId,
		private int $posted,
		private int $replyCount
	) {}

	public function id(): int {
		return $this->id;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function poster(): string {
		return $this->poster;
	}

	public function firstPostId(): int {
		return $this->firstPostId;
	}

	public function posted(): int {
		return $this->posted;
	}

	public function replyCount(): int {
		return $this->replyCount;
	}
}
