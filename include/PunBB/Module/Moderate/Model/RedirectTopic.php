<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Model;

use PunBB\Module\Moderate\Api\Data\RedirectTopicInterface;

final readonly class RedirectTopic implements RedirectTopicInterface {
	public function __construct(
		private string $poster,
		private string $subject,
		private int $posted,
		private int $lastPost,
		private int $movedTo,
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

	public function lastPost(): int {
		return $this->lastPost;
	}

	public function movedTo(): int {
		return $this->movedTo;
	}

	public function forumId(): int {
		return $this->forumId;
	}
}
