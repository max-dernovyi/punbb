<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Users\Api\Data\FoundUserInterface;
use PunBB\Module\Users\Api\Data\UserBanInterface;
use PunBB\Module\Users\Api\Data\UserSearchInterface;
use PunBB\Module\Users\Api\UsersInterface;

final class UsersInterceptor implements UsersInterface {
	public function __construct(private readonly UsersInterface $subject, private readonly PluginChain $plugins) {}

	public function addressesOf(int $userId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->addressesOf(...));
	}

	public function postersFrom(string $address): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->postersFrom(...));
	}

	public function member(int $id): ?FoundUserInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->member(...));
	}

	public function count(UserSearchInterface $search): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->count(...));
	}

	public function find(UserSearchInterface $search, int $offset, int $limit): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->find(...));
	}

	public function searchGroups(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->searchGroups(...));
	}

	public function includesAdministrators(int ...$ids): bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->includesAdministrators(...));
	}

	public function postAddresses(int ...$ids): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->postAddresses(...));
	}

	public function banTargets(int ...$ids): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->banTargets(...));
	}

	public function ban(UserBanInterface ...$bans): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->ban(...));
	}

	public function groupModerates(int $id): ?bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->groupModerates(...));
	}

	public function moveToGroup(int $groupId, int ...$ids): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->moveToGroup(...));
	}

	public function moveTargets(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->moveTargets(...));
	}
}
