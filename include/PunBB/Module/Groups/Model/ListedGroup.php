<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Model;

use PunBB\Module\Groups\Api\Data\ListedGroupInterface;

final readonly class ListedGroup implements ListedGroupInterface {
	public function __construct(private int $id, private string $title) {}

	public function id(): int {
		return $this->id;
	}

	public function title(): string {
		return $this->title;
	}
}
