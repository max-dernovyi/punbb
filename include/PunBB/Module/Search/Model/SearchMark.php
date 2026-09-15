<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Model;

use PunBB\Module\Search\Api\Data\SearchMarkInterface;

final readonly class SearchMark implements SearchMarkInterface {
	public function __construct(private ?int $memberId, private string $address, private int $at) {}

	public function memberId(): ?int {
		return $this->memberId;
	}

	public function address(): string {
		return $this->address;
	}

	public function at(): int {
		return $this->at;
	}
}
