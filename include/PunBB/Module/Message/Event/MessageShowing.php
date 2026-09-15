<?php

declare(strict_types=1);

namespace PunBB\Module\Message\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * A message about to be shown, as markup: what it says, the link after it and
 * its heading, '' for the default one.
 */
final class MessageShowing implements EventInterface {
	public function __construct(private string $message, private string $link, private string $heading) {}

	public function message(): string {
		return $this->message;
	}

	public function link(): string {
		return $this->link;
	}

	public function heading(): string {
		return $this->heading;
	}

	public function change(string $message, string $link, string $heading): void {
		$this->message = $message;
		$this->link = $link;
		$this->heading = $heading;
	}
}
