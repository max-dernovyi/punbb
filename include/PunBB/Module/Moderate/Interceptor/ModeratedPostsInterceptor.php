<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Moderate\Api\Data\FirstPostInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedTopicInterface;
use PunBB\Module\Moderate\Api\Data\NewTopicInterface;
use PunBB\Module\Moderate\Api\ModeratedPostsInterface;

final class ModeratedPostsInterceptor implements ModeratedPostsInterface {
	public function __construct(private readonly ModeratedPostsInterface $subject, private readonly PluginChain $plugins) {}

	public function posterAddress(int $postId): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->posterAddress(...));
	}

	public function topic(int $id, int $forumId): ?ModeratedTopicInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->topic(...));
	}

	public function countReplies(int $topicId, int $firstPostId, int ...$postIds): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->countReplies(...));
	}

	public function deletePosts(int ...$postIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->deletePosts(...));
	}

	public function firstPost(int $id): ?FirstPostInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->firstPost(...));
	}

	public function addTopics(NewTopicInterface ...$topics): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->addTopics(...));
	}

	public function lastTopicId(): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->lastTopicId(...));
	}

	public function movePosts(int $topicId, int ...$postIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->movePosts(...));
	}

	public function posts(int $topicId, int $offset, int $limit): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->posts(...));
	}
}
