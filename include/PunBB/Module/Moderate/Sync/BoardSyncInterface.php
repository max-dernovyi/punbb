<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Sync;

/**
 * The counts and last posts a topic and a forum keep, brought in line with
 * the posts they hold once posts or topics moved or went. Deleting and pruning
 * sync them too, with points of their own there: the module declares it, the
 * bootstrap's side wires it.
 */
interface BoardSyncInterface {
	public function topic(int $topicId): void;

	public function forum(int $forumId): void;
}
