<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Model;

use PunBB\Module\Groups\Api\Data\GroupMembersInterface;

final readonly class GroupMembers implements GroupMembersInterface {
	public function __construct(private string $title, private int $count) {}

	public function title(): string {
		return $this->title;
	}

	public function count(): int {
		return $this->count;
	}
}
