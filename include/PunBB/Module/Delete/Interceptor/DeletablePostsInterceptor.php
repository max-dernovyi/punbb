<?php

declare(strict_types=1);

namespace PunBB\Module\Delete\Interceptor;

use PunBB\Module\Delete\Api\Data\DeletablePostInterface;
use PunBB\Module\Delete\Api\DeletablePostsInterface;
use PunBB\Module\Framework\Plugin\PluginChain;

final class DeletablePostsInterceptor implements DeletablePostsInterface {
	public function __construct(private readonly DeletablePostsInterface $subject, private readonly PluginChain $plugins) {}

	public function find(int $postId, int $groupId): ?DeletablePostInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->find(...));
	}

	public function previousPostId(int $topicId, int $postId): ?int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->previousPostId(...));
	}
}
