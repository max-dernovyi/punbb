<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Model;

use PunBB\Module\Install\Api\Data\RankInterface;

final readonly class Rank implements RankInterface {
	public function __construct(private string $title, private int $minPosts) {}

	public function title(): string {
		return $this->title;
	}

	public function minPosts(): int {
		return $this->minPosts;
	}
}
