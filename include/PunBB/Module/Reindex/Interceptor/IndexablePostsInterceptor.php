<?php

declare(strict_types=1);

namespace PunBB\Module\Reindex\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Reindex\Api\IndexablePostsInterface;

final class IndexablePostsInterceptor implements IndexablePostsInterface {
	public function __construct(private readonly IndexablePostsInterface $subject, private readonly PluginChain $plugins) {}

	public function firstId(): ?int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->firstId(...));
	}

	public function batch(int $startAt, int $limit): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->batch(...));
	}

	public function nextId(int $postId): ?int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->nextId(...));
	}
}
