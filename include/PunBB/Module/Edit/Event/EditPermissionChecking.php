<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Event;

use PunBB\Module\Edit\Api\Data\EditablePostInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * The post was found, and whether the visitor may edit it is about to be
 * decided. A moderator of its forum may edit any post; an observer may change
 * whether the visitor counts as one.
 */
final class EditPermissionChecking implements EventInterface {
	public function __construct(private readonly EditablePostInterface $post, private bool $moderating) {}

	public function post(): EditablePostInterface {
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
