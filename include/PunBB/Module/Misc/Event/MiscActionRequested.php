<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * misc.php was asked for something it does not know, before the request is refused.
 */
final class MiscActionRequested implements EventInterface {
	/** @param string $action the action asked for; '' when none was */
	public function __construct(private readonly string $action) {}

	public function action(): string {
		return $this->action;
	}
}
