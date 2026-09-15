<?php

declare(strict_types=1);

namespace PunBB\Module\Delete\Event;

use PunBB\Module\Delete\Api\Data\DeletablePostInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * The post was found, and whether the visitor may delete it is about to be
 * decided. A moderator of its forum may delete any post; an observer may
 * change whether the visitor counts as one.
 */
final class DeletionPermissionChecking implements EventInterface {
	public function __construct(private readonly DeletablePostInterface $post, private bool $moderating) {}

	public function post(): DeletablePostInterface {
		return $this->post;
	}

	/** Whether the visitor administers the board or moderates the post's forum. */
	public function moderating(): bool {
		return $this->moderating;
	}

	public function treatAsModerating(bool $moderating): void {
		$this->moderating = $moderating;
	}
}
