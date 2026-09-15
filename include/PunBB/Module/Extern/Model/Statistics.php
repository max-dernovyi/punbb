<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Model;

use PunBB\Module\Extern\Api\Data\StatisticsInterface;

final readonly class Statistics implements StatisticsInterface {
	public function __construct(private int $userCount, private int $newestUserId, private string $newestUsername, private int $topicCount, private int $postCount) {}

	public function userCount(): int {
		return $this->userCount;
	}

	public function newestUserId(): int {
		return $this->newestUserId;
	}

	public function newestUsername(): string {
		return $this->newestUsername;
	}

	public function topicCount(): int {
		return $this->topicCount;
	}

	public function postCount(): int {
		return $this->postCount;
	}
}
