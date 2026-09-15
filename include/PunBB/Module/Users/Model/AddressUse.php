<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Model;

use PunBB\Module\Users\Api\Data\AddressUseInterface;

final readonly class AddressUse implements AddressUseInterface {
	public function __construct(private string $address, private int $lastUsed, private int $timesUsed) {}

	public function address(): string {
		return $this->address;
	}

	public function lastUsed(): int {
		return $this->lastUsed;
	}

	public function timesUsed(): int {
		return $this->timesUsed;
	}
}
