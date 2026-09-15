<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Model;

use PunBB\Module\Post\Api\Data\ReviewPostInterface;

final readonly class ReviewPost implements ReviewPostInterface {
	public function __construct(private int $id, private string $poster, private string $message, private bool $hidesSmilies, private int $postedAt) {}

	public function id(): int {
		return $this->id;
	}

	public function poster(): string {
		return $this->poster;
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
}
