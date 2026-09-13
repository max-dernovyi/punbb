<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Event;

use Closure;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleException;
use ReflectionObject;

/**
 * Hands an event to the observers the modules bound to its class, in module
 * order, then declaration order. Each observer receives the event and nothing
 * else, and changes it only through the methods its class declares.
 */
final class EventDispatcher {
	/** @var array<string, list<ObserverDeclaration>> event => its observers */
	private readonly array $declarations;

	/** @var array<string, list<array{class-string, Closure}>> event => its observers' observe(), built on first dispatch */
	private array $observers = array();

	/** @param list<ObserverDeclaration> $observers in module order, then declaration order */
	public function __construct(array $observers, private readonly Container $container) {
		$byEvent = array();
		foreach ($observers as $observer)
			$byEvent[$observer->event][] = $observer;

		$this->declarations = $byEvent;
	}

	public function dispatch(EventInterface $event): void {
		foreach ($this->observers($event::class) as [$class, $observe])
		{
			$observe($event);

			// PHP 8.2+ only deprecates a dynamic property; on an event it is a data bag.
			foreach ((new ReflectionObject($event))->getProperties() as $property)
				if ($property->isDynamic())
					throw new EventException(sprintf('%s added $%s to %s; an observer changes an event only through the methods its class declares', $class, $property->getName(), $event::class));
		}
	}

	/** @return list<array{class-string, Closure}> */
	private function observers(string $event): array {
		if (isset($this->observers[$event]))
			return $this->observers[$event];

		$observers = array();
		foreach ($this->declarations[$event] ?? array() as $declaration)
		{
			$observer = ($declaration->factory)($this->container);
			if (!$observer instanceof $declaration->class || !method_exists($observer, 'observe'))
				throw new ModuleException(sprintf('Module %s wired observer %s to a %s', $declaration->module, $declaration->class, $observer::class));

			$observers[] = array($declaration->class, $observer->observe(...));
		}

		return $this->observers[$event] = $observers;
	}
}
