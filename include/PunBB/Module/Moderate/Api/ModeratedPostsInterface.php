<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Api;

use PunBB\Module\Moderate\Api\Data\FirstPostInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedPostInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedTopicInterface;
use PunBB\Module\Moderate\Api\Data\NewTopicInterface;

/**
 * A topic's posts as a moderator changes them: deleted, or split off into a
 * topic of their own; and the address a post was written from.
 */
interface ModeratedPostsInterface {
	/** The address post $postId was written from; null when there is no such post, or it recorded none. */
	public function posterAddress(int $postId): ?string;

	/** Topic $id in forum $forumId; null when there is none there, or it is a redirect to a topic moved elsewhere. */
	public function topic(int $id, int $forumId): ?ModeratedTopicInterface;

	/** How many of the posts $postIds are replies in topic $topicId, which $firstPostId started. */
	public function countReplies(int $topicId, int $firstPostId, int ...$postIds): int;

	/** Removes the posts $postIds. */
	public function deletePosts(int ...$postIds): void;

	/** Post $id, which starts a topic split off; null when there is none. */
	public function firstPost(int $id): ?FirstPostInterface;

	/** Stores each of $topics. */
	public function addTopics(NewTopicInterface ...$topics): void;

	/** The id of the topic added last. */
	public function lastTopicId(): int;

	/** Moves the posts $postIds into topic $topicId. */
	public function movePosts(int $topicId, int ...$postIds): void;

	/** @return list<ModeratedPostInterface> at most $limit of topic $topicId's posts, after the first $offset, the oldest first */
	public function posts(int $topicId, int $offset, int $limit): array;
}
