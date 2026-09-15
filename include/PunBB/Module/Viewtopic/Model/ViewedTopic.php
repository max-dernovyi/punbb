<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Model;

use PunBB\Module\Viewtopic\Api\Data\ModeratorInterface;
use PunBB\Module\Viewtopic\Api\Data\ViewedTopicInterface;

final readonly class ViewedTopic implements ViewedTopicInterface {
	/** @param list<ModeratorInterface> $moderators */
	public function __construct(
		private int $id,
		private string $subject,
		private int $firstPostId,
		private bool $closed,
		private bool $sticky,
		private int $replyCount,
		private int $forumId,
		private string $forumName,
		private array $moderators,
		private ?bool $groupPostsReplies,
		private bool $subscribed
	) {}

	public function id(): int {
		return $this->id;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function firstPostId(): int {
		return $this->firstPostId;
	}

	public function isClosed(): bool {
		return $this->closed;
	}

	public function isSticky(): bool {
		return $this->sticky;
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

	public function moderators(): array {
		return $this->moderators;
	}

	public function groupPostsReplies(): ?bool {
		return $this->groupPostsReplies;
	}

	public function isSubscribed(): bool {
		return $this->subscribed;
	}
}
