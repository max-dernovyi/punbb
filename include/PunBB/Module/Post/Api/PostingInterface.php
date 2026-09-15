<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Api;

use PunBB\Module\Post\Api\Data\LocationInterface;
use PunBB\Module\Post\Api\Data\QuoteInterface;
use PunBB\Module\Post\Api\Data\ReviewPostInterface;

/**
 * Where members post, what they quote, and the posts a reply is written below.
 */
interface PostingInterface {
	/** Topic $topicId with its forum, as member $userId of group $groupId replies to it; null when there is none they may read. */
	public function topic(int $topicId, int $groupId, int $userId): ?LocationInterface;

	/** Forum $forumId, as a member of group $groupId starts a topic in it; null when there is none they may read. */
	public function forum(int $forumId, int $groupId): ?LocationInterface;

	/** Post $postId of topic $topicId, to quote; null when the topic has no such post. */
	public function quote(int $postId, int $topicId): ?QuoteInterface;

	/** How many posts topic $topicId has. */
	public function reviewCount(int $topicId): int;

	/** @return list<ReviewPostInterface> the newest $limit posts of topic $topicId, newest first */
	public function review(int $topicId, int $limit): array;
}
