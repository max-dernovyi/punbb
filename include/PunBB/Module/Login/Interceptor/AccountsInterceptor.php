<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Login\Api\AccountsInterface;
use PunBB\Module\Login\Api\Data\CredentialsInterface;
use PunBB\Module\Login\Api\Data\LastVisitInterface;
use PunBB\Module\Login\Api\Data\ResetKeyInterface;

final class AccountsInterceptor implements AccountsInterface {
	public function __construct(private readonly AccountsInterface $subject, private readonly PluginChain $plugins) {}

	public function credentials(string $username): ?CredentialsInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->credentials(...));
	}

	public function storePassword(CredentialsInterface ...$credentials): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->storePassword(...));
	}

	public function activate(int $groupId, int ...$userIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->activate(...));
	}

	public function recordLastVisit(LastVisitInterface ...$visits): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->recordLastVisit(...));
	}

	public function resettable(string $email): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->resettable(...));
	}

	public function issueResetKey(ResetKeyInterface ...$keys): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->issueResetKey(...));
	}
}
