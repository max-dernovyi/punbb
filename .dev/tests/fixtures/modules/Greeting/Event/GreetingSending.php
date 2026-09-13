<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Greeting\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;

/**
 * A greeting on its way out: an observer may reword it, and nothing else.
 */
final class GreetingSending implements EventInterface {
	public function __construct(private GreetingInterface $greeting) {}

	public function greeting(): GreetingInterface {
		return $this->greeting;
	}

	public function reword(string $text): void {
		$this->greeting = $this->greeting->withText($text);
	}
}
