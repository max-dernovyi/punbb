<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Api;

use PunBB\Module\Viewforum\Api\Data\ListedTopicInterface;
use PunBB\Module\Viewforum\Api\Data\ViewedForumInterface;

/**
 * A forum and the topics it lists, a page at a time.
 */
interface ForumTopicsInterface {
	/**
	 * Forum $forumId as a member of group $groupId sees it; null when there is
	 * none or the group may not read it. With $subscriberId, whether that
	 * member is subscribed to it.
	 */
	public function forum(int $forumId, int $groupId, ?int $subscriberId): ?ViewedForumInterface;

	/**
	 * The ids of a page of the forum's topics: sticky ones first, then by when
	 * they were posted or last posted in, newest first.
	 *
	 * @return list<int>
	 */
	public function topicIds(int $forumId, bool $byPosted, int $offset, int $limit): array;

	/**
	 * The topics $topicIds, in the order topicIds() gives them. With $posterId,
	 * whether that member posted in each.
	 *
	 * @param list<int> $topicIds
	 * @return list<ListedTopicInterface>
	 */
	public function topics(array $topicIds, bool $byPosted, ?int $posterId): array;
}
