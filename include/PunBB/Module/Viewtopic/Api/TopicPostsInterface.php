<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Api;

use PunBB\Module\Viewtopic\Api\Data\PostLocationInterface;
use PunBB\Module\Viewtopic\Api\Data\TopicPostInterface;
use PunBB\Module\Viewtopic\Api\Data\ViewedTopicInterface;

/**
 * A topic and its posts, a page at a time.
 */
interface TopicPostsInterface {
	/** Where post $postId is; null when there is no such post. */
	public function locate(int $postId): ?PostLocationInterface;

	/** How many posts of topic $topicId were posted before $posted. */
	public function countBefore(int $topicId, int $posted): int;

	/** The first post of topic $topicId posted after $after; null when there is none. */
	public function firstPostAfter(int $topicId, int $after): ?int;

	/** The last post of topic $topicId; null when there is none. */
	public function lastPostId(int $topicId): ?int;

	/**
	 * Topic $topicId as a member of group $groupId sees it; null when there is
	 * none, it only points at one it was moved to, or the group may not read
	 * its forum. With $subscriberId, whether that member is subscribed to it.
	 */
	public function topic(int $topicId, int $groupId, ?int $subscriberId): ?ViewedTopicInterface;

	/** @return list<int> the ids of a page of the topic's posts, in the order of their ids */
	public function postIds(int $topicId, int $offset, int $limit): array;

	/**
	 * @param list<int> $postIds
	 * @return list<TopicPostInterface> the posts $postIds with their posters, in the order of their ids
	 */
	public function posts(array $postIds): array;

	/** Counts a view of each of topics $topicIds. */
	public function countView(int ...$topicIds): void;
}
