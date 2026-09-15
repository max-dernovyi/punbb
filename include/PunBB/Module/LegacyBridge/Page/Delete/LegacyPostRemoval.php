<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Delete;

use PunBB\Module\Delete\Removal\PostRemovalInterface;

/**
 * delete_topic() and delete_post() of include/functions.php, with the extension
 * code attached to them and to the search index and forum sync they call.
 */
final class LegacyPostRemoval implements PostRemovalInterface {
	public function removeTopic(int $topicId, int $forumId): void {
		\delete_topic($topicId, $forumId);
	}

	public function removePost(int $postId, int $topicId, int $forumId): void {
		\delete_post($postId, $topicId, $forumId);
	}
}
