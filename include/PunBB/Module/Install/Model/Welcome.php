<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Model;

use PunBB\Module\Install\Api\Data\WelcomeInterface;

final readonly class Welcome implements WelcomeInterface {
	public function __construct(
		private string $category,
		private string $forum,
		private string $forumDescription,
		private string $subject,
		private string $message,
		private string $poster,
		private int $posterId,
		private int $posted
	) {}

	public function category(): string {
		return $this->category;
	}

	public function forum(): string {
		return $this->forum;
	}

	public function forumDescription(): string {
		return $this->forumDescription;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function message(): string {
		return $this->message;
	}

	public function poster(): string {
		return $this->poster;
	}

	public function posterId(): int {
		return $this->posterId;
	}

	public function posted(): int {
		return $this->posted;
	}
}
