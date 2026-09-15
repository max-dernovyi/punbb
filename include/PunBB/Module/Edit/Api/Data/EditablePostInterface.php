<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Api\Data;

/**
 * A post the edit page shows: the post, its topic and its forum.
 */
interface EditablePostInterface {
	public function id(): int;

	public function forumId(): int;

	public function forumName(): string;

	/** @return list<ModeratorInterface> the forum's moderators */
	public function moderators(): array;

	public function topicId(): int;

	public function subject(): string;

	/** The post that opens its topic. */
	public function firstPostId(): int;

	/** Whether it opens its topic, so its edit may change the topic's subject. */
	public function isTopic(): bool;

	public function topicClosed(): bool;

	public function poster(): string;

	public function posterId(): int;

	public function message(): string;

	public function hidesSmilies(): bool;
}
