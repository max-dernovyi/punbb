<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Event;

use Closure;
use PunBB\Module\Framework\Container\Container;

/**
 * One observer a module binds to an event.
 */
final readonly class ObserverDeclaration {
	/**
	 * @param class-string<EventInterface> $event
	 * @param class-string $class
	 * @param Closure(Container): object $factory builds the observer, resolving its constructor arguments from the container
	 */
	public function __construct(
		public string $module,
		public string $event,
		public string $class,
		public Closure $factory
	) {}
}
