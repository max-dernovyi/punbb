<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * Syndication was asked for an action it does not know, before the request is refused.
 */
final class ExternActionRequested implements EventInterface {
	public function __construct(private readonly string $action) {}

	public function action(): string {
		return $this->action;
	}
}
