<?php

declare(strict_types=1);

namespace PunBB\Module\Prune\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Prune\Api\PrunableTopicsInterface;

final class PrunableTopicsInterceptor implements PrunableTopicsInterface {
	public function __construct(private readonly PrunableTopicsInterface $subject, private readonly PluginChain $plugins) {}

	public function forums(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forums(...));
	}

	public function forumIds(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forumIds(...));
	}

	public function forumName(int $forumId): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forumName(...));
	}

	public function count(?int $forumId, int $lastPostBefore, bool $sticky): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->count(...));
	}
}
