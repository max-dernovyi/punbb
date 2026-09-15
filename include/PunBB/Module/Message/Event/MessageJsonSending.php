<?php

declare(strict_types=1);

namespace PunBB\Module\Message\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * A message about to be sent to a script as JSON: its code and its markup.
 */
final class MessageJsonSending implements EventInterface {
	public function __construct(private int $code, private string $message) {}

	public function code(): int {
		return $this->code;
	}

	public function message(): string {
		return $this->message;
	}

	public function change(int $code, string $message): void {
		$this->code = $code;
		$this->message = $message;
	}
}
