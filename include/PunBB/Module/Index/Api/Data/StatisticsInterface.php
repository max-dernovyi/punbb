<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Api\Data;

/**
 * The board's figures: its members, the newest of them, its topics and posts.
 */
interface StatisticsInterface {
	/** The registered members who confirmed their address. */
	public function userCount(): int;

	/** The member who registered last; 0 when there is none. */
	public function newestUserId(): int;

	public function newestUsername(): string;

	public function topicCount(): int;

	public function postCount(): int;
}
