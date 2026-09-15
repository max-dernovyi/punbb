<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Model;

use PunBB\Module\Users\Api\Data\FoundUserInterface;

final readonly class FoundUser implements FoundUserInterface {
	public function __construct(
		private int $id,
		private string $username,
		private string $email,
		private string $title,
		private int $postCount,
		private string $adminNote,
		private ?int $groupId,
		private ?string $groupTitle
	) {}

	public function id(): int {
		return $this->id;
	}

	public function username(): string {
		return $this->username;
	}

	public function email(): string {
		return $this->email;
	}

	public function title(): string {
		return $this->title;
	}

	public function postCount(): int {
		return $this->postCount;
	}

	public function adminNote(): string {
		return $this->adminNote;
	}

	public function groupId(): ?int {
		return $this->groupId;
	}

	public function groupTitle(): ?string {
		return $this->groupTitle;
	}
}
