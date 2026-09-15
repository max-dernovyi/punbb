<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Api;

use PunBB\Module\Misc\Api\Data\SubscriptionInterface;

/**
 * Members' subscriptions to topics and forums, and what a subscription checks first.
 */
interface SubscriptionsInterface {
	/** The subject of topic $topicId, when group $groupId may read it and it was not moved; null otherwise. */
	public function topicSubject(int $topicId, int $groupId): ?string;

	public function isSubscribedToTopic(int $userId, int $topicId): bool;

	public function subscribeToTopic(SubscriptionInterface ...$subscriptions): void;

	/** The subject of topic $topicId, when member $userId is subscribed to it; null otherwise. */
	public function subscribedTopicSubject(int $userId, int $topicId): ?string;

	public function unsubscribeFromTopic(SubscriptionInterface ...$subscriptions): void;

	/** The name of forum $forumId, when group $groupId may read it; null otherwise. */
	public function forumName(int $forumId, int $groupId): ?string;

	public function isSubscribedToForum(int $userId, int $forumId): bool;

	public function subscribeToForum(SubscriptionInterface ...$subscriptions): void;

	/** The name of forum $forumId, when group $groupId may read it; null otherwise. Unsubscribing asks it before removing what there is. */
	public function unsubscribingForumName(int $forumId, int $groupId): ?string;

	public function unsubscribeFromForum(SubscriptionInterface ...$subscriptions): void;
}
