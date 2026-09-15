<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Api\Data;

/**
 * A topic whose posts are being moderated.
 */
interface ModeratedTopicInterface {
	public function id(): int;

	public function subject(): string;

	public function poster(): string;

	/** The post that started the topic, which cannot be deleted or split off on its own. */
	public function firstPostId(): int;

	public function posted(): int;

	public function replyCount(): int;
}
