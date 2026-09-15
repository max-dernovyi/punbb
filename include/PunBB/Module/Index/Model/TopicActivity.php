<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Model;

use PunBB\Module\Index\Api\Data\TopicActivityInterface;

final readonly class TopicActivity implements TopicActivityInterface {
	public function __construct(private int $forumId, private int $topicId, private int $lastPost) {}

	public function forumId(): int {
		return $this->forumId;
	}

	public function topicId(): int {
		return $this->topicId;
	}

	public function lastPost(): int {
		return $this->lastPost;
	}
}
