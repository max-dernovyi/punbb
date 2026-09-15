<?php

declare(strict_types=1);

namespace PunBB\Module\Message\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * A request whose token did not match is about to be asked for confirmation.
 * An observer may let it through unconfirmed instead.
 */
final class ConfirmFormRequested implements EventInterface {
	private bool $letThrough = false;

	public function letThrough(): void {
		$this->letThrough = true;
	}

	public function isLetThrough(): bool {
		return $this->letThrough;
	}
}
