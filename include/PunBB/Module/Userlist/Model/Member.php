<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Model;

use PunBB\Module\Userlist\Api\Data\MemberInterface;

final readonly class Member implements MemberInterface {
	public function __construct(
		private int $id,
		private string $username,
		private string $title,
		private int $postCount,
		private int $registered,
		private ?int $groupId,
		private ?string $groupTitle
	) {}

	public function id(): int {
		return $this->id;
	}

	public function username(): string {
		return $this->username;
	}

	public function title(): string {
		return $this->title;
	}

	public function postCount(): int {
		return $this->postCount;
	}

	public function registered(): int {
		return $this->registered;
	}

	public function groupId(): ?int {
		return $this->groupId;
	}

	public function groupTitle(): ?string {
		return $this->groupTitle;
	}
}
