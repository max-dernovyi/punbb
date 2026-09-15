<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Search\Api\ResultsInterface;

final class ResultsInterceptor implements ResultsInterface {
	public function __construct(private readonly ResultsInterface $subject, private readonly PluginChain $plugins) {}

	public function posts(array $postIds, ?int $sortBy, string $sortDir): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->posts(...));
	}

	public function topics(array $topicIds, ?int $sortBy, string $sortDir, ?int $posterId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->topics(...));
	}

	public function newTopics(int $groupId, int $since, ?int $forumId, ?int $posterId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->newTopics(...));
	}

	public function recentTopics(int $groupId, int $since, ?int $posterId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->recentTopics(...));
	}

	public function userPosts(int $groupId, int $userId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->userPosts(...));
	}

	public function userTopics(int $groupId, int $userId, ?int $posterId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->userTopics(...));
	}

	public function subscribedTopics(int $groupId, int $userId, ?int $posterId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->subscribedTopics(...));
	}

	public function subscribedForums(int $groupId, int $userId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->subscribedForums(...));
	}

	public function unansweredTopics(int $groupId, ?int $posterId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->unansweredTopics(...));
	}

	public function forums(int $groupId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forums(...));
	}
}
