<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Moderate\Api\Data\MergeTargetInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;
use PunBB\Module\Moderate\Api\Data\MovedTopicInterface;
use PunBB\Module\Moderate\Api\Data\RedirectTopicInterface;
use PunBB\Module\Moderate\Api\ModeratedTopicsInterface;

final class ModeratedTopicsInterceptor implements ModeratedTopicsInterface {
	public function __construct(private readonly ModeratedTopicsInterface $subject, private readonly PluginChain $plugins) {}

	public function forum(int $id, int $groupId): ?ModeratedForumInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forum(...));
	}

	public function topics(int $forumId, bool $byPosted, int $offset, int $limit, ?int $postedBy): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->topics(...));
	}

	public function subject(int $id): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->subject(...));
	}

	public function subjectIn(int $id, int $forumId): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->subjectIn(...));
	}

	public function moveTargets(int $exceptId, int $groupId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->moveTargets(...));
	}

	public function forumName(int $id): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forumName(...));
	}

	public function countTopics(int $forumId, int ...$topicIds): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->countTopics(...));
	}

	public function removeRedirects(int $forumId, int ...$topicIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removeRedirects(...));
	}

	public function moveTopics(int $forumId, int ...$topicIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->moveTopics(...));
	}

	public function movedTopic(int $id): ?MovedTopicInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->movedTopic(...));
	}

	public function addRedirects(RedirectTopicInterface ...$redirects): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->addRedirects(...));
	}

	public function mergeTarget(int $forumId, int ...$topicIds): MergeTargetInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->mergeTarget(...));
	}

	public function redirectMerged(int $toId, bool $leaveRedirects, int ...$topicIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->redirectMerged(...));
	}

	public function mergePosts(int $toId, int ...$topicIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->mergePosts(...));
	}

	public function removeMergedSubscriptions(int $toId, int ...$topicIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removeMergedSubscriptions(...));
	}

	public function removeMergedTopics(int $toId, int ...$topicIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removeMergedTopics(...));
	}

	public function redirectForums(int ...$topicIds): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->redirectForums(...));
	}

	public function removeTopics(int ...$topicIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removeTopics(...));
	}

	public function removeSubscriptions(int ...$topicIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removeSubscriptions(...));
	}

	public function postIds(int ...$topicIds): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->postIds(...));
	}

	public function removePosts(int ...$topicIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removePosts(...));
	}

	public function closeTopics(bool $closed, int $forumId, int ...$topicIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->closeTopics(...));
	}

	public function stickTopics(bool $sticky, int $forumId, int ...$topicIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->stickTopics(...));
	}
}
