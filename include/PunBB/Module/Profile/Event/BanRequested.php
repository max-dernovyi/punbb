<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * Banning a member was asked for from their profile, before the browser is
 * sent to the bans page's form.
 */
final class BanRequested implements EventInterface {
	public function __construct(private readonly ProfileUserInterface $user) {}

	public function user(): ProfileUserInterface {
		return $this->user;
	}
}
