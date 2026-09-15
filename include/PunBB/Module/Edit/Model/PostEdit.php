<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Model;

use PunBB\Module\Edit\Api\Data\PostEditInterface;

final readonly class PostEdit implements PostEditInterface {
	public function __construct(
		private int $postId,
		private int $topicId,
		private ?string $subject,
		private string $message,
		private bool $hidesSmilies,
		private ?int $editedAt,
		private ?string $editedBy
	) {}

	public function postId(): int {
		return $this->postId;
	}

	public function topicId(): int {
		return $this->topicId;
	}

	public function subject(): ?string {
		return $this->subject;
	}

	public function message(): string {
		return $this->message;
	}

	public function hidesSmilies(): bool {
		return $this->hidesSmilies;
	}

	public function editedAt(): ?int {
		return $this->editedAt;
	}

	public function editedBy(): ?string {
		return $this->editedBy;
	}
}
