<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Model;

use PunBB\Module\Post\Api\Data\LocationInterface;
use PunBB\Module\Post\Api\Data\ModeratorInterface;

final readonly class Location implements LocationInterface {
	/** @param list<ModeratorInterface> $moderators */
	public function __construct(
		private int $forumId,
		private string $forumName,
		private array $moderators,
		private string $redirectUrl,
		private ?bool $groupPostsReplies,
		private ?bool $groupPostsTopics,
		private int $topicId = 0,
		private string $subject = '',
		private bool $topicClosed = false,
		private bool $subscribed = false
	) {}

	public function forumId(): int {
		return $this->forumId;
	}

	public function forumName(): string {
		return $this->forumName;
	}

	public function moderators(): array {
		return $this->moderators;
	}

	public function redirectUrl(): string {
		return $this->redirectUrl;
	}

	public function groupPostsReplies(): ?bool {
		return $this->groupPostsReplies;
	}

	public function groupPostsTopics(): ?bool {
		return $this->groupPostsTopics;
	}

	public function topicId(): int {
		return $this->topicId;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function topicClosed(): bool {
		return $this->topicClosed;
	}

	public function subscribed(): bool {
		return $this->subscribed;
	}
}
