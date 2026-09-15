<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Interceptor;

use PunBB\Module\Edit\Api\Data\EditablePostInterface;
use PunBB\Module\Edit\Api\Data\PostEditInterface;
use PunBB\Module\Edit\Api\EditablePostsInterface;
use PunBB\Module\Framework\Plugin\PluginChain;

final class EditablePostsInterceptor implements EditablePostsInterface {
	public function __construct(private readonly EditablePostsInterface $subject, private readonly PluginChain $plugins) {}

	public function find(int $postId, int $groupId): ?EditablePostInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->find(...));
	}

	public function renameTopic(PostEditInterface ...$edits): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->renameTopic(...));
	}

	public function saveMessage(PostEditInterface ...$edits): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->saveMessage(...));
	}
}
