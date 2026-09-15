<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Viewforum\Api\Data\ViewedForumInterface;
use PunBB\Module\Viewforum\Api\ForumTopicsInterface;

final class ForumTopicsInterceptor implements ForumTopicsInterface {
	public function __construct(private readonly ForumTopicsInterface $subject, private readonly PluginChain $plugins) {}

	public function forum(int $forumId, int $groupId, ?int $subscriberId): ?ViewedForumInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forum(...));
	}

	public function topicIds(int $forumId, bool $byPosted, int $offset, int $limit): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->topicIds(...));
	}

	public function topics(array $topicIds, bool $byPosted, ?int $posterId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->topics(...));
	}
}
