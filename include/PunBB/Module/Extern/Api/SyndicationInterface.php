<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Api;

use PunBB\Module\Extern\Api\Data\FeedEntryInterface;
use PunBB\Module\Extern\Api\Data\FeedTopicInterface;
use PunBB\Module\Extern\Api\Data\OnlineVisitorInterface;
use PunBB\Module\Extern\Api\Data\StatisticsInterface;

/**
 * What the board syndicates to other sites: the recent topics and posts, who
 * is online, and its statistics.
 */
interface SyndicationInterface {
	/** Topic $topicId as a member of group $groupId may read it, unless it was moved; null otherwise. */
	public function topic(int $topicId, int $groupId): ?FeedTopicInterface;

	/** @return list<FeedEntryInterface> the $limit posts of topic $topicId posted last, the last first */
	public function posts(int $topicId, int $limit): array;

	/** Forum $forumId's name as group $groupId may read it; null otherwise. */
	public function forumName(int $forumId, int $groupId): ?string;

	/**
	 * The $limit topics group $groupId may read, newest first by when they were
	 * posted or last posted in, each with its first post; only those in
	 * $forumIds, or with $excluding those outside them, unless it is empty.
	 *
	 * @param list<int> $forumIds
	 * @return list<FeedEntryInterface>
	 */
	public function topics(int $groupId, array $forumIds, bool $excluding, bool $byLastPost, int $limit): array;

	/** @return list<OnlineVisitorInterface> who is online and not idle, by name */
	public function onlineVisitors(): array;

	public function statistics(): StatisticsInterface;
}
