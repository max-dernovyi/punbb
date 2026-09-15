<?php

declare(strict_types=1);

namespace PunBB\Module\Delete\Removal;

/**
 * Takes a post or a whole topic off the board: its rows, its words in the
 * search index, its subscriptions, and the counts and last posts of the
 * forums it was in. The module declares it; the bootstrap's side wires it.
 */
interface PostRemovalInterface {
	/** Topic $topicId of forum $forumId, every post in it, and the redirects left where it was moved from. */
	public function removeTopic(int $topicId, int $forumId): void;

	/** Post $postId, a reply in topic $topicId of forum $forumId. */
	public function removePost(int $postId, int $topicId, int $forumId): void;
}
