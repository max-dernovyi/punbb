<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Post\Api\Data\LocationInterface;

/**
 * Where the visitor posts was found, and whether they may post there is about
 * to be decided. A moderator of the forum may post where the forum or the
 * topic would not let them; an observer may change whether the visitor counts
 * as one.
 */
final class PostingPermissionChecking implements EventInterface {
	public function __construct(private readonly LocationInterface $location, private bool $moderating) {}

	public function location(): LocationInterface {
		return $this->location;
	}

	/** Whether the visitor administers the board or moderates the forum. */
	public function moderating(): bool {
		return $this->moderating;
	}

	public function treatAsModerating(bool $moderating): void {
		$this->moderating = $moderating;
	}
}
