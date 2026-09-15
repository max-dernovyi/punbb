<?php

declare(strict_types=1);

namespace PunBB\Module\Delete\Api\Data;

/**
 * A post the deletion page shows: the post, its topic and its forum.
 */
interface DeletablePostInterface {
	public function id(): int;

	public function forumId(): int;

	public function forumName(): string;

	/** @return list<ModeratorInterface> the forum's moderators */
	public function moderators(): array;

	public function topicId(): int;

	public function subject(): string;

	/** The post that opens its topic. */
	public function firstPostId(): int;

	/** Whether it opens its topic, so deleting it deletes the topic. */
	public function isTopic(): bool;

	public function topicClosed(): bool;

	public function poster(): string;

	public function posterId(): int;

	public function message(): string;

	public function hidesSmilies(): bool;

	public function posted(): int;
}
