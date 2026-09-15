<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * A section the profile does not have was asked for by a visitor who may edit
 * the profile, before the request is refused: an observer may answer a
 * section of its own.
 */
final class ProfileSectionRequested implements EventInterface {
	public function __construct(private readonly ProfileUserInterface $user, private readonly string $section) {}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	public function section(): string {
		return $this->section;
	}
}
