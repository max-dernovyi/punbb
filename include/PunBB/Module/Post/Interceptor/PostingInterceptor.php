<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Post\Api\Data\LocationInterface;
use PunBB\Module\Post\Api\Data\QuoteInterface;
use PunBB\Module\Post\Api\PostingInterface;

final class PostingInterceptor implements PostingInterface {
	public function __construct(private readonly PostingInterface $subject, private readonly PluginChain $plugins) {}

	public function topic(int $topicId, int $groupId, int $userId): ?LocationInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->topic(...));
	}

	public function forum(int $forumId, int $groupId): ?LocationInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forum(...));
	}

	public function quote(int $postId, int $topicId): ?QuoteInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->quote(...));
	}

	public function reviewCount(int $topicId): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->reviewCount(...));
	}

	public function review(int $topicId, int $limit): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->review(...));
	}
}
