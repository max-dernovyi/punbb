<?php

declare(strict_types=1);

namespace PunBB\Module\Ranks\Model;

use PunBB\Module\Ranks\Api\Data\RankInterface;

final readonly class Rank implements RankInterface {
	public function __construct(private int $id, private string $title, private int $minPosts) {}

	public function id(): int {
		return $this->id;
	}

	public function title(): string {
		return $this->title;
	}

	public function minPosts(): int {
		return $this->minPosts;
	}
}
