<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Removal;

/**
 * Empties a forum about to be removed: its topics, their posts, subscriptions
 * and words in the search index. The categories and the forums pages share it.
 */
interface ForumContentsInterface {
	/** Takes every topic of forum $forumId off the board, sticky ones too. */
	public function empty(int $forumId): void;

	/** Removes the topics left pointing at a topic that is gone. */
	public function removeOrphans(): void;
}
