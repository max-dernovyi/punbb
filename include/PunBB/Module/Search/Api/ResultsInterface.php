<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Api;

use PunBB\Module\Search\Api\Data\ResultForumInterface;
use PunBB\Module\Search\Api\Data\ResultPostInterface;
use PunBB\Module\Search\Api\Data\ResultTopicInterface;
use PunBB\Module\Search\Api\Data\SearchableForumInterface;

/**
 * What a results page lists: what a stored search found, and the topics,
 * posts and forums each quick search finds, as a member of the group asked
 * about may read them. Where $posterId is given, each topic says whether that
 * member posted in it.
 */
interface ResultsInterface {
	/**
	 * Posts $postIds, sorted as a stored search asks.
	 *
	 * @param list<int> $postIds
	 * @return list<ResultPostInterface>
	 */
	public function posts(array $postIds, ?int $sortBy, string $sortDir): array;

	/**
	 * Topics $topicIds, sorted as a stored search asks.
	 *
	 * @param list<int> $topicIds
	 * @return list<ResultTopicInterface>
	 */
	public function topics(array $topicIds, ?int $sortBy, string $sortDir, ?int $posterId): array;

	/**
	 * Topics posted in since $since, in forum $forumId or in every forum when it is null, most recently posted in first.
	 *
	 * @return list<ResultTopicInterface>
	 */
	public function newTopics(int $groupId, int $since, ?int $forumId, ?int $posterId): array;

	/**
	 * Topics posted in since $since, most recently posted in first.
	 *
	 * @return list<ResultTopicInterface>
	 */
	public function recentTopics(int $groupId, int $since, ?int $posterId): array;

	/**
	 * Member $userId's posts, newest first.
	 *
	 * @return list<ResultPostInterface>
	 */
	public function userPosts(int $groupId, int $userId): array;

	/**
	 * The topics member $userId started, most recently posted in first.
	 *
	 * @return list<ResultTopicInterface>
	 */
	public function userTopics(int $groupId, int $userId, ?int $posterId): array;

	/**
	 * The topics member $userId subscribes to, most recently posted in first.
	 *
	 * @return list<ResultTopicInterface>
	 */
	public function subscribedTopics(int $groupId, int $userId, ?int $posterId): array;

	/**
	 * The forums member $userId subscribes to, in the board's order.
	 *
	 * @return list<ResultForumInterface>
	 */
	public function subscribedForums(int $groupId, int $userId): array;

	/**
	 * Topics nobody replied to, most recently posted in first.
	 *
	 * @return list<ResultTopicInterface>
	 */
	public function unansweredTopics(int $groupId, ?int $posterId): array;

	/**
	 * The forums of this board the search form offers, in the board's order.
	 *
	 * @return list<SearchableForumInterface>
	 */
	public function forums(int $groupId): array;
}
