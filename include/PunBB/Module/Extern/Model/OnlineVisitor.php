<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Model;

use PunBB\Module\Extern\Api\Data\OnlineVisitorInterface;

final readonly class OnlineVisitor implements OnlineVisitorInterface {
	/** The guest account, which every guest online is listed as. */
	public const GUEST = 1;

	public function __construct(private int $userId, private string $ident) {}

	public function userId(): int {
		return $this->userId;
	}

	public function ident(): string {
		return $this->ident;
	}

	public function isGuest(): bool {
		return $this->userId <= self::GUEST;
	}
}
