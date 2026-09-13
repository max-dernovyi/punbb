<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Greeting\Model;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;
use PunBBFixture\Module\Greeting\Event\GreetingSending;

final class Postman {
	public function __construct(private readonly EventDispatcher $events) {}

	public function send(GreetingInterface $greeting): GreetingInterface {
		$event = new GreetingSending($greeting);
		$this->events->dispatch($event);

		return $event->greeting();
	}
}
