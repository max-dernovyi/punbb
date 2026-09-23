<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Update\Api\BoardDataInterface;
use PunBB\Module\Update\Api\Data\PostRangeInterface;

final class BoardDataInterceptor implements BoardDataInterface {
	public function __construct(private readonly BoardDataInterface $subject, private readonly PluginChain $plugins) {}

	public function spareGroupId(): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->spareGroupId(...));
	}

	public function reorderGroups(int $spare, int $step): bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->reorderGroups(...));
	}

	public function hasModeratorGroup(): bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->hasModeratorGroup(...));
	}

	public function grantModerators(string $permission, int $value): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->grantModerators(...));
	}

	public function limitGroupMail(): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->limitGroupMail(...));
	}

	public function recordFirstPosts(): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->recordFirstPosts(...));
	}

	public function moveUnverifiedUsers(): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->moveUnverifiedUsers(...));
	}

	public function supersededHotfixes(string $version): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->supersededHotfixes(...));
	}

	public function removeExtension(string $id): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removeExtension(...));
	}

	public function schemeLinkedinAddresses(): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->schemeLinkedinAddresses(...));
	}

	public function storeAvatar(int $userId, int $type, int $width, int $height): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->storeAvatar(...));
	}

	public function postRange(): PostRangeInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->postRange(...));
	}

	public function postText(int $postId): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->postText(...));
	}

	public function forumIds(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forumIds(...));
	}

	public function syncForum(int $forumId): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->syncForum(...));
	}

	public function emptySearchCache(): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->emptySearchCache(...));
	}

	public function emptyOnline(): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->emptyOnline(...));
	}
}
