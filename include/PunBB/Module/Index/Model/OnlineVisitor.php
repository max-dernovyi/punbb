<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Model;

use PunBB\Module\Index\Api\Data\OnlineVisitorInterface;

final readonly class OnlineVisitor implements OnlineVisitorInterface {
	public function __construct(private int $userId, private string $ident) {}

	public function userId(): int {
		return $this->userId;
	}

	public function ident(): string {
		return $this->ident;
	}

	public function isGuest(): bool {
		return $this->userId <= 1;
	}
}
