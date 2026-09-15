<?php

declare(strict_types=1);

namespace PunBB\Module\Message\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The maintenance message is about to be shown to a visitor who is not an
 * administrator. An observer may let the visitor in instead.
 */
final class MaintenanceShowing implements EventInterface {
	private bool $letIn = false;

	public function letIn(): void {
		$this->letIn = true;
	}

	public function isLetIn(): bool {
		return $this->letIn;
	}
}
