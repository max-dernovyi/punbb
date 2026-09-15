<?php

declare(strict_types=1);

namespace PunBB\Module\Message\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * A redirect about to be shown, before its destination is made safe to send
 * the browser to: where it leads, and its message as markup.
 */
final class RedirectShowing implements EventInterface {
	public function __construct(private string $destination, private string $message) {}

	public function destination(): string {
		return $this->destination;
	}

	public function message(): string {
		return $this->message;
	}

	public function change(string $destination, string $message): void {
		$this->destination = $destination;
		$this->message = $message;
	}
}
