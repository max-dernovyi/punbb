<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Model;

use PunBB\Module\Profile\Api\Data\ModeratorInterface;
use PunBB\Module\Profile\Api\Data\ForumModeratorsInterface;

final readonly class ForumModerators implements ForumModeratorsInterface {
	/** @param list<ModeratorInterface> $moderators */
	public function __construct(private int $forumId, private array $moderators) {}

	public function forumId(): int {
		return $this->forumId;
	}

	public function moderators(): array {
		return $this->moderators;
	}
}
