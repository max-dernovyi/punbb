<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Model;

use PunBB\Module\Moderate\Api\Data\ModeratedPostInterface;

final readonly class ModeratedPost implements ModeratedPostInterface {
	public function __construct(
		private int $id,
		private string $poster,
		private int $posterId,
		private string $message,
		private bool $hidesSmilies,
		private int $posted,
		private ?int $edited,
		private string $editedBy,
		private string $posterTitle,
		private int $posterPostCount,
		private int $posterGroupId,
		private ?string $posterGroupTitle
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

	public function message(): string {
		return $this->message;
	}

	public function hidesSmilies(): bool {
		return $this->hidesSmilies;
	}

	public function posted(): int {
		return $this->posted;
	}

	public function edited(): ?int {
		return $this->edited;
	}

	public function editedBy(): string {
		return $this->editedBy;
	}

	public function posterTitle(): string {
		return $this->posterTitle;
	}

	public function posterPostCount(): int {
		return $this->posterPostCount;
	}

	public function posterGroupId(): int {
		return $this->posterGroupId;
	}

	public function posterGroupTitle(): ?string {
		return $this->posterGroupTitle;
	}
}
