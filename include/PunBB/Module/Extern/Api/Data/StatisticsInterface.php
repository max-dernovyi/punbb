<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Api\Data;

/**
 * The board's figures: its members, the newest of them, its topics and posts.
 */
interface StatisticsInterface {
	public function userCount(): int;

	public function newestUserId(): int;

	public function newestUsername(): string;

	public function topicCount(): int;

	public function postCount(): int;
}
