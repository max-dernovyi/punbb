<?php

declare(strict_types=1);

namespace PunBB\Module\Delete\Api;

use PunBB\Module\Delete\Api\Data\DeletablePostInterface;

/**
 * The posts a deletion is asked for: each with its topic and forum.
 */
interface DeletablePostsInterface {
	/** Post $postId as a member of group $groupId sees it; null when there is none or the group may not read its forum. */
	public function find(int $postId, int $groupId): ?DeletablePostInterface;

	/** The post before $postId in topic $topicId; null when it is the first left. */
	public function previousPostId(int $topicId, int $postId): ?int;
}
