<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Interceptor;

use PunBB\Module\Bans\Api\BanCandidatesInterface;
use PunBB\Module\Bans\Api\Data\BanCandidateInterface;
use PunBB\Module\Framework\Plugin\PluginChain;

final class BanCandidatesInterceptor implements BanCandidatesInterface {
	public function __construct(private readonly BanCandidatesInterface $subject, private readonly PluginChain $plugins) {}

	public function byId(int $userId): ?BanCandidateInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->byId(...));
	}

	public function byUsername(string $username): ?BanCandidateInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->byUsername(...));
	}

	public function lastKnownIp(int $userId): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->lastKnownIp(...));
	}
}
