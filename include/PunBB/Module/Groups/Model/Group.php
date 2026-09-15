<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Model;

use PunBB\Module\Groups\Api\Data\GroupInterface;
use PunBB\Module\Site\Visitor\GroupPermission;

final readonly class Group implements GroupInterface {
	/** @param list<GroupPermission> $permissions what the group allows */
	public function __construct(
		private int $id,
		private string $title,
		private ?string $userTitle,
		private array $permissions,
		private int $postFlood,
		private int $searchFlood,
		private int $emailFlood
	) {}

	public function id(): int {
		return $this->id;
	}

	public function title(): string {
		return $this->title;
	}

	public function userTitle(): ?string {
		return $this->userTitle;
	}

	public function allows(string $permission): bool {
		return in_array(GroupPermission::tryFrom($permission), $this->permissions, true);
	}

	public function postFlood(): int {
		return $this->postFlood;
	}

	public function searchFlood(): int {
		return $this->searchFlood;
	}

	public function emailFlood(): int {
		return $this->emailFlood;
	}

	/** This group without $permission. */
	public function without(GroupPermission $permission): self {
		return new self($this->id, $this->title, $this->userTitle, array_values(array_filter($this->permissions, static fn (GroupPermission $allowed): bool => $allowed !== $permission)), $this->postFlood, $this->searchFlood, $this->emailFlood);
	}
}
