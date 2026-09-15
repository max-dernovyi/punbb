<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Interceptor;

use PunBB\Module\Extern\Api\Data\FeedTopicInterface;
use PunBB\Module\Extern\Api\Data\StatisticsInterface;
use PunBB\Module\Extern\Api\SyndicationInterface;
use PunBB\Module\Framework\Plugin\PluginChain;

final class SyndicationInterceptor implements SyndicationInterface {
	public function __construct(private readonly SyndicationInterface $subject, private readonly PluginChain $plugins) {}

	public function topic(int $topicId, int $groupId): ?FeedTopicInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->topic(...));
	}

	public function posts(int $topicId, int $limit): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->posts(...));
	}

	public function forumName(int $forumId, int $groupId): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forumName(...));
	}

	public function topics(int $groupId, array $forumIds, bool $excluding, bool $byLastPost, int $limit): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->topics(...));
	}

	public function onlineVisitors(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->onlineVisitors(...));
	}

	public function statistics(): StatisticsInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->statistics(...));
	}
}
