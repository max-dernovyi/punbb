<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Model;

use PunBB\Module\Extern\Api\Data\FeedEntryInterface;

final readonly class FeedEntry implements FeedEntryInterface {
	public function __construct(
		private int $id,
		private string $subject,
		private string $poster,
		private int $posterId,
		private int $posted,
		private string $message,
		private bool $hidesSmilies,
		private string $accountEmail,
		private bool $showsEmail,
		private string $guestEmail
	) {}

	public function id(): int {
		return $this->id;
	}

	public function subject(): string {
		return $this->subject;
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

	public function message(): string {
		return $this->message;
	}

	public function hidesSmilies(): bool {
		return $this->hidesSmilies;
	}

	public function accountEmail(): string {
		return $this->accountEmail;
	}

	public function showsEmail(): bool {
		return $this->showsEmail;
	}

	public function guestEmail(): string {
		return $this->guestEmail;
	}
}
