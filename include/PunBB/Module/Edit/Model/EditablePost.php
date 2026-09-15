<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Model;

use PunBB\Module\Edit\Api\Data\EditablePostInterface;
use PunBB\Module\Edit\Api\Data\ModeratorInterface;

final readonly class EditablePost implements EditablePostInterface {
	/** @param list<ModeratorInterface> $moderators */
	public function __construct(
		private int $id,
		private int $forumId,
		private string $forumName,
		private array $moderators,
		private int $topicId,
		private string $subject,
		private int $firstPostId,
		private bool $topicClosed,
		private string $poster,
		private int $posterId,
		private string $message,
		private bool $hidesSmilies
	) {}

	public function id(): int {
		return $this->id;
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

	public function topicId(): int {
		return $this->topicId;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function firstPostId(): int {
		return $this->firstPostId;
	}

	public function isTopic(): bool {
		return $this->id === $this->firstPostId;
	}

	public function topicClosed(): bool {
		return $this->topicClosed;
	}

	public function poster(): string {
		return $this->poster;
	}

	public function posterId(): int {
		return $this->posterId;
	}

	public function message(): string {
		return $this->message;
	}

	public function hidesSmilies(): bool {
		return $this->hidesSmilies;
	}
}
