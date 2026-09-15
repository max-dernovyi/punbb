<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Model;

use PunBB\Module\Misc\Api\Data\SubscriptionInterface;

final readonly class Subscription implements SubscriptionInterface {
	public function __construct(private int $userId, private int $targetId) {}

	public function userId(): int {
		return $this->userId;
	}

	public function targetId(): int {
		return $this->targetId;
	}
}
