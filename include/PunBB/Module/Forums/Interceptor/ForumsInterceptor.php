<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Interceptor;

use PunBB\Module\Forums\Api\Data\ForumInterface;
use PunBB\Module\Forums\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Forums\Api\Data\ForumPositionInterface;
use PunBB\Module\Forums\Api\ForumsInterface;
use PunBB\Module\Framework\Plugin\PluginChain;

final class ForumsInterceptor implements ForumsInterface {
	public function __construct(private readonly ForumsInterface $subject, private readonly PluginChain $plugins) {}

	public function all(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->all(...));
	}

	public function categories(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->categories(...));
	}

	public function assignableCategories(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->assignableCategories(...));
	}

	public function categoryExists(int $id): bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->categoryExists(...));
	}

	public function add(ForumInterface ...$forums): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->add(...));
	}

	public function find(int $id): ?ForumInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->find(...));
	}

	public function name(int $id): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->name(...));
	}

	public function update(ForumInterface ...$forums): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->update(...));
	}

	public function positions(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->positions(...));
	}

	public function reposition(ForumPositionInterface ...$positions): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->reposition(...));
	}

	public function remove(int ...$ids): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->remove(...));
	}

	public function removePermissions(int ...$forumIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removePermissions(...));
	}

	public function removeSubscriptions(int ...$forumIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removeSubscriptions(...));
	}

	public function groupDefaults(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->groupDefaults(...));
	}

	public function groupPermissions(int $forumId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->groupPermissions(...));
	}

	public function permissionsStored(int $forumId, int $groupId): bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->permissionsStored(...));
	}

	public function updatePermissions(int $forumId, ForumPermissionsInterface ...$permissions): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->updatePermissions(...));
	}

	public function addPermissions(int $forumId, ForumPermissionsInterface ...$permissions): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->addPermissions(...));
	}

	public function removeGroupPermissions(int $forumId, int ...$groupIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removeGroupPermissions(...));
	}

	public function revertPermissions(int ...$forumIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->revertPermissions(...));
	}
}
