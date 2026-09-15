<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;

/**
 * The forum was found, and whether the visitor may moderate it is about to be
 * decided: an administrator may, and a member of a moderating group the forum
 * lists as its moderator. An observer may change whether the visitor counts as one.
 */
final class ModeratorChecking implements EventInterface {
	public function __construct(private readonly ModeratedForumInterface $forum, private bool $moderating) {}

	public function forum(): ModeratedForumInterface {
		return $this->forum;
	}

	public function moderating(): bool {
		return $this->moderating;
	}

	public function treatAsModerating(bool $moderating): void {
		$this->moderating = $moderating;
	}
}
