<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Api;

use PunBB\Module\Edit\Api\Data\EditablePostInterface;
use PunBB\Module\Edit\Api\Data\PostEditInterface;

/**
 * The posts members edit.
 */
interface EditablePostsInterface {
	/** Post $postId, when a member of group $groupId may read its forum; null otherwise. */
	public function find(int $postId, int $groupId): ?EditablePostInterface;

	/** Gives the topic of each of $edits, and every topic moved away from it, the edit's subject. */
	public function renameTopic(PostEditInterface ...$edits): void;

	/** Stores the message of each of $edits, and who edited it when unless the edit is silent. */
	public function saveMessage(PostEditInterface ...$edits): void;
}
