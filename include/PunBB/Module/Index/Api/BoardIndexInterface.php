<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Api;

use PunBB\Module\Index\Api\Data\ForumInterface;
use PunBB\Module\Index\Api\Data\OnlineVisitorInterface;
use PunBB\Module\Index\Api\Data\StatisticsInterface;
use PunBB\Module\Index\Api\Data\TopicActivityInterface;

/**
 * What the board index lists: the forums a group may read, where there is
 * activity, the board's figures and who is online.
 */
interface BoardIndexInterface {
	/** @return list<ForumInterface> the forums group $groupId may read, by category, in display order */
	public function forums(int $groupId): array;

	/** @return list<TopicActivityInterface> the topics group $groupId may read with a post after $since */
	public function activeTopics(int $groupId, int $since): array;

	public function statistics(): StatisticsInterface;

	/** @return list<OnlineVisitorInterface> the visitors online now, by name */
	public function onlineVisitors(): array;
}
