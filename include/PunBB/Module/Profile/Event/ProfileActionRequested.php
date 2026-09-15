<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * No action the profile has was asked for, or a section's form was refused,
 * before the profile or the section is shown: an observer may answer an action
 * of its own.
 */
final class ProfileActionRequested implements EventInterface {
	public function __construct(private readonly ProfileUserInterface $user, private readonly string $section, private readonly ?string $action) {}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	public function section(): string {
		return $this->section;
	}

	/** The action asked for; null for none. */
	public function action(): ?string {
		return $this->action;
	}
}
