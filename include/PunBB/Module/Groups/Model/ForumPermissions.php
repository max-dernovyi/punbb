<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Model;

use PunBB\Module\Groups\Api\Data\ForumPermissionsInterface;

final readonly class ForumPermissions implements ForumPermissionsInterface {
	public function __construct(private int $forumId, private bool $readForum, private bool $postReplies, private bool $postTopics) {}

	public function forumId(): int {
		return $this->forumId;
	}

	public function readForum(): bool {
		return $this->readForum;
	}

	public function postReplies(): bool {
		return $this->postReplies;
	}

	public function postTopics(): bool {
		return $this->postTopics;
	}
}
