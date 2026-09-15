<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Model;

use PunBB\Module\Forums\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Forums\Api\Data\GroupDefaultsInterface;

final readonly class ForumPermissions implements ForumPermissionsInterface {
	public function __construct(private int $groupId, private bool $readForum, private bool $postReplies, private bool $postTopics) {}

	/** What group $defaults may do on the board, as the permissions it has in a forum storing none. */
	public static function defaults(GroupDefaultsInterface $defaults): self {
		return new self($defaults->groupId(), $defaults->readsBoard(), $defaults->postsReplies(), $defaults->postsTopics());
	}

	public function groupId(): int {
		return $this->groupId;
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
