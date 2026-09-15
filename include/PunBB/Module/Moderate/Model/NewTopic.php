<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Model;

use PunBB\Module\Moderate\Api\Data\NewTopicInterface;

final readonly class NewTopic implements NewTopicInterface {
	public function __construct(
		private string $poster,
		private string $subject,
		private int $posted,
		private int $firstPostId,
		private int $forumId
	) {}

	public function poster(): string {
		return $this->poster;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function posted(): int {
		return $this->posted;
	}

	public function firstPostId(): int {
		return $this->firstPostId;
	}

	public function forumId(): int {
		return $this->forumId;
	}
}
