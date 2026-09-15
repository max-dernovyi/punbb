<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Profile\Api\Data\AvatarInterface;
use PunBB\Module\Profile\Api\Data\DetailsInterface;
use PunBB\Module\Profile\Api\Data\EmailActivationInterface;
use PunBB\Module\Profile\Api\Data\EmailChangeInterface;
use PunBB\Module\Profile\Api\Data\ForumModeratorsInterface;
use PunBB\Module\Profile\Api\Data\PasswordInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Api\Data\RenameInterface;
use PunBB\Module\Profile\Api\ProfilesInterface;

final class ProfilesInterceptor implements ProfilesInterface {
	public function __construct(private readonly ProfilesInterface $subject, private readonly PluginChain $plugins) {}

	public function user(int $id): ?ProfileUserInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->user(...));
	}

	public function resetPassword(PasswordInterface ...$passwords): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->resetPassword(...));
	}

	public function changePassword(PasswordInterface ...$passwords): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->changePassword(...));
	}

	public function confirmEmail(int ...$userIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->confirmEmail(...));
	}

	public function usernamesWithEmail(string $email): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->usernamesWithEmail(...));
	}

	public function changeEmail(EmailChangeInterface ...$changes): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->changeEmail(...));
	}

	public function requestEmailChange(EmailActivationInterface ...$activations): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->requestEmailChange(...));
	}

	public function moveToGroup(int $groupId, int ...$userIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->moveToGroup(...));
	}

	public function groupModerates(int $groupId): bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->groupModerates(...));
	}

	public function forumModerators(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forumModerators(...));
	}

	public function storeModerators(ForumModeratorsInterface ...$forums): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->storeModerators(...));
	}

	public function storeAvatar(AvatarInterface ...$avatars): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->storeAvatar(...));
	}

	public function updateDetails(DetailsInterface ...$details): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->updateDetails(...));
	}

	public function renamePosts(RenameInterface ...$renames): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->renamePosts(...));
	}

	public function renameTopics(RenameInterface ...$renames): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->renameTopics(...));
	}

	public function renameTopicLastPosters(RenameInterface ...$renames): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->renameTopicLastPosters(...));
	}

	public function renameForumLastPosters(RenameInterface ...$renames): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->renameForumLastPosters(...));
	}

	public function renameOnline(RenameInterface ...$renames): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->renameOnline(...));
	}

	public function renameEditors(RenameInterface ...$renames): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->renameEditors(...));
	}

	public function groups(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->groups(...));
	}

	public function moderatableForums(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->moderatableForums(...));
	}
}
