<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Groups\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Groups\Api\Data\GroupInterface;
use PunBB\Module\Groups\Api\Data\GroupMembersInterface;
use PunBB\Module\Groups\Api\GroupsInterface;

final class GroupsInterceptor implements GroupsInterface {
	public function __construct(private readonly GroupsInterface $subject, private readonly PluginChain $plugins) {}

	public function all(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->all(...));
	}

	public function baseGroups(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->baseGroups(...));
	}

	public function defaultCandidates(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->defaultCandidates(...));
	}

	public function moveTargets(int $id): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->moveTargets(...));
	}

	public function baseGroup(int $id): ?GroupInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->baseGroup(...));
	}

	public function group(int $id): ?GroupInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->group(...));
	}

	public function titleTaken(string $title, ?int $exceptId): bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->titleTaken(...));
	}

	public function add(GroupInterface ...$groups): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->add(...));
	}

	public function lastAddedId(): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->lastAddedId(...));
	}

	public function update(GroupInterface ...$groups): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->update(...));
	}

	public function forumPermissions(int $groupId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forumPermissions(...));
	}

	public function addForumPermissions(int $groupId, ForumPermissionsInterface ...$permissions): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->addForumPermissions(...));
	}

	public function isDefaultCandidate(int $id): bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->isDefaultCandidate(...));
	}

	public function makeDefault(int ...$ids): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->makeDefault(...));
	}

	public function members(int $id): ?GroupMembersInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->members(...));
	}

	public function moveMembers(int $toId, int ...$fromIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->moveMembers(...));
	}

	public function remove(int ...$ids): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->remove(...));
	}

	public function removeForumPermissions(int ...$groupIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removeForumPermissions(...));
	}
}
