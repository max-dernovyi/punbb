<?php

declare(strict_types=1);

namespace PunBB\Module\Censoring\Model;

use PunBB\Module\Censoring\Api\Data\CensorInterface;

final readonly class Censor implements CensorInterface {
	public function __construct(private int $id, private string $searchFor, private string $replaceWith) {}

	public function id(): int {
		return $this->id;
	}

	public function searchFor(): string {
		return $this->searchFor;
	}

	public function replaceWith(): string {
		return $this->replaceWith;
	}
}
