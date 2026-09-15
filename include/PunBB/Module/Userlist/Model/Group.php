<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Model;

use PunBB\Module\Userlist\Api\Data\GroupInterface;

final readonly class Group implements GroupInterface {
	public function __construct(private int $id, private string $title) {}

	public function id(): int {
		return $this->id;
	}

	public function title(): string {
		return $this->title;
	}
}
