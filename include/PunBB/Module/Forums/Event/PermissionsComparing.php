<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Event;

use PunBB\Module\Forums\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Forums\Api\Data\GroupDefaultsInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A group's permissions in a saved forum, before they are compared: what the
 * group may do on the board, what the form showed and what it submitted. An
 * observer may change any of the three; the permissions are stored when what
 * was submitted differs from what was shown, and removed when it is the default.
 */
final class PermissionsComparing implements EventInterface {
	public function __construct(
		private readonly int $forumId,
		private readonly GroupDefaultsInterface $group,
		private ForumPermissionsInterface $defaults,
		private ForumPermissionsInterface $shown,
		private ForumPermissionsInterface $submitted
	) {}

	public function forumId(): int {
		return $this->forumId;
	}

	public function group(): GroupDefaultsInterface {
		return $this->group;
	}

	public function defaults(): ForumPermissionsInterface {
		return $this->defaults;
	}

	public function shown(): ForumPermissionsInterface {
		return $this->shown;
	}

	public function submitted(): ForumPermissionsInterface {
		return $this->submitted;
	}

	public function change(ForumPermissionsInterface $defaults, ForumPermissionsInterface $shown, ForumPermissionsInterface $submitted): void {
		$this->defaults = $defaults;
		$this->shown = $shown;
		$this->submitted = $submitted;
	}
}
