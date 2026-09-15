<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Misc\Api\Data\SubscriptionInterface;
use PunBB\Module\Misc\Api\SubscriptionsInterface;

final class SubscriptionsInterceptor implements SubscriptionsInterface {
	public function __construct(private readonly SubscriptionsInterface $subject, private readonly PluginChain $plugins) {}

	public function topicSubject(int $topicId, int $groupId): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->topicSubject(...));
	}

	public function isSubscribedToTopic(int $userId, int $topicId): bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->isSubscribedToTopic(...));
	}

	public function subscribeToTopic(SubscriptionInterface ...$subscriptions): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->subscribeToTopic(...));
	}

	public function subscribedTopicSubject(int $userId, int $topicId): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->subscribedTopicSubject(...));
	}

	public function unsubscribeFromTopic(SubscriptionInterface ...$subscriptions): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->unsubscribeFromTopic(...));
	}

	public function forumName(int $forumId, int $groupId): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forumName(...));
	}

	public function isSubscribedToForum(int $userId, int $forumId): bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->isSubscribedToForum(...));
	}

	public function subscribeToForum(SubscriptionInterface ...$subscriptions): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->subscribeToForum(...));
	}

	public function unsubscribingForumName(int $forumId, int $groupId): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->unsubscribingForumName(...));
	}

	public function unsubscribeFromForum(SubscriptionInterface ...$subscriptions): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->unsubscribeFromForum(...));
	}
}
