<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Model;

use PunBB\Module\Post\Api\Data\NewPostInterface;

final readonly class NewPost implements NewPostInterface {
	public function __construct(
		private bool $isGuest,
		private string $poster,
		private int $posterId,
		private ?string $posterEmail,
		private string $subject,
		private string $message,
		private bool $hidesSmilies,
		private int $postedAt,
		private int $topicId,
		private int $forumId,
		private string $forumName,
		private int $subscription
	) {}

	public function isGuest(): bool {
		return $this->isGuest;
	}

	public function poster(): string {
		return $this->poster;
	}

	public function posterId(): int {
		return $this->posterId;
	}

	public function posterEmail(): ?string {
		return $this->posterEmail;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function message(): string {
		return $this->message;
	}

	public function hidesSmilies(): bool {
		return $this->hidesSmilies;
	}

	public function postedAt(): int {
		return $this->postedAt;
	}

	public function topicId(): int {
		return $this->topicId;
	}

	public function forumId(): int {
		return $this->forumId;
	}

	public function forumName(): string {
		return $this->forumName;
	}

	public function subscription(): int {
		return $this->subscription;
	}
}
