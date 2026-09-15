<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Viewtopic\Api\Data\PostLocationInterface;
use PunBB\Module\Viewtopic\Api\Data\ViewedTopicInterface;
use PunBB\Module\Viewtopic\Api\TopicPostsInterface;

final class TopicPostsInterceptor implements TopicPostsInterface {
	public function __construct(private readonly TopicPostsInterface $subject, private readonly PluginChain $plugins) {}

	public function locate(int $postId): ?PostLocationInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->locate(...));
	}

	public function countBefore(int $topicId, int $posted): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->countBefore(...));
	}

	public function firstPostAfter(int $topicId, int $after): ?int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->firstPostAfter(...));
	}

	public function lastPostId(int $topicId): ?int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->lastPostId(...));
	}

	public function topic(int $topicId, int $groupId, ?int $subscriberId): ?ViewedTopicInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->topic(...));
	}

	public function postIds(int $topicId, int $offset, int $limit): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->postIds(...));
	}

	public function posts(array $postIds): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->posts(...));
	}

	public function countView(int ...$topicIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->countView(...));
	}
}
