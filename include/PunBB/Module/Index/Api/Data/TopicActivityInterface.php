<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Api\Data;

/**
 * A topic's last post, and the forum it is in.
 */
interface TopicActivityInterface {
	public function forumId(): int;

	public function topicId(): int;

	public function lastPost(): int;
}
