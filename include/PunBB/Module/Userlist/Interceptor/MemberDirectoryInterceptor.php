<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Userlist\Api\Data\MemberSearchInterface;
use PunBB\Module\Userlist\Api\MemberDirectoryInterface;

final class MemberDirectoryInterceptor implements MemberDirectoryInterface {
	public function __construct(private readonly MemberDirectoryInterface $subject, private readonly PluginChain $plugins) {}

	public function count(MemberSearchInterface $search): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->count(...));
	}

	public function find(MemberSearchInterface $search, int $offset, int $limit): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->find(...));
	}

	public function groups(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->groups(...));
	}
}
