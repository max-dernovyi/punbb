<?php

declare(strict_types=1);

namespace PunBBModule\Guestbook\Model;

use PunBB\Module\Index\Api\Data\StatisticsInterface;

/**
 * The board's figures with the guestbook's entries among its posts.
 */
final readonly class CountedStatistics implements StatisticsInterface {
	public function __construct(private StatisticsInterface $board, private int $entries) {}

	public function userCount(): int {
		return $this->board->userCount();
	}

	public function newestUserId(): int {
		return $this->board->newestUserId();
	}

	public function newestUsername(): string {
		return $this->board->newestUsername();
	}

	public function topicCount(): int {
		return $this->board->topicCount();
	}

	public function postCount(): int {
		return $this->board->postCount() + $this->entries;
	}
}
