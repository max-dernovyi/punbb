<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Api;

use PunBB\Module\Moderate\Api\Data\ListedTopicInterface;
use PunBB\Module\Moderate\Api\Data\MergeTargetInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;
use PunBB\Module\Moderate\Api\Data\MovedTopicInterface;
use PunBB\Module\Moderate\Api\Data\RedirectTopicInterface;
use PunBB\Module\Moderate\Api\Data\TargetForumInterface;

/**
 * A forum's topics as a moderator changes them: moved to another forum,
 * merged, deleted, opened or closed, stuck or unstuck.
 */
interface ModeratedTopicsInterface {
	/** Forum $id as group $groupId may read it; null when there is none, or the group may not. */
	public function forum(int $id, int $groupId): ?ModeratedForumInterface;

	/** @return list<ListedTopicInterface> at most $limit of forum $forumId's topics, after the first $offset, sticky ones first, then the latest started or posted in; whether $postedBy posted in each when given */
	public function topics(int $forumId, bool $byPosted, int $offset, int $limit, ?int $postedBy): array;

	/** The subject of topic $id, in whichever forum; null when there is none. */
	public function subject(int $id): ?string;

	/** The subject of topic $id of forum $forumId; null when the forum holds no such topic. */
	public function subjectIn(int $id, int $forumId): ?string;

	/** @return list<TargetForumInterface> every forum group $groupId may read but forum $exceptId and those on another site, by category and position */
	public function moveTargets(int $exceptId, int $groupId): array;

	/** The name of forum $id; null when there is none. */
	public function forumName(int $id): ?string;

	/** How many of the topics $topicIds forum $forumId holds. */
	public function countTopics(int $forumId, int ...$topicIds): int;

	/** Removes the redirects forum $forumId holds to any of the topics $topicIds. */
	public function removeRedirects(int $forumId, int ...$topicIds): void;

	/** Moves the topics $topicIds into forum $forumId. */
	public function moveTopics(int $forumId, int ...$topicIds): void;

	/** Topic $id, as a redirect to it shows it; null when there is none. */
	public function movedTopic(int $id): ?MovedTopicInterface;

	/** Stores each of $redirects. */
	public function addRedirects(RedirectTopicInterface ...$redirects): void;

	/** The topics $topicIds forum $forumId holds that are not redirects, counted, and the oldest of them. */
	public function mergeTarget(int $forumId, int ...$topicIds): MergeTargetInterface;

	/** Points every redirect to the topics $topicIds at topic $toId, and turns the topics themselves but $toId into such redirects when $leaveRedirects. */
	public function redirectMerged(int $toId, bool $leaveRedirects, int ...$topicIds): void;

	/** Moves every post of the topics $topicIds into topic $toId. */
	public function mergePosts(int $toId, int ...$topicIds): void;

	/** Removes the subscriptions to the topics $topicIds but $toId. */
	public function removeMergedSubscriptions(int $toId, int ...$topicIds): void;

	/** Removes the topics $topicIds but $toId. */
	public function removeMergedTopics(int $toId, int ...$topicIds): void;

	/** @return list<int> the forums holding a redirect to any of the topics $topicIds */
	public function redirectForums(int ...$topicIds): array;

	/** Removes the topics $topicIds and every redirect to them. */
	public function removeTopics(int ...$topicIds): void;

	/** Removes the subscriptions to the topics $topicIds. */
	public function removeSubscriptions(int ...$topicIds): void;

	/** @return list<int> every post of the topics $topicIds */
	public function postIds(int ...$topicIds): array;

	/** Removes every post of the topics $topicIds. */
	public function removePosts(int ...$topicIds): void;

	/** Opens the topics $topicIds forum $forumId holds, or closes them when $closed. */
	public function closeTopics(bool $closed, int $forumId, int ...$topicIds): void;

	/** Sticks the topics $topicIds forum $forumId holds, or unsticks them. */
	public function stickTopics(bool $sticky, int $forumId, int ...$topicIds): void;
}
