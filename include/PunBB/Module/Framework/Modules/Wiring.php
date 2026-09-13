<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Modules;

use Closure;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Framework\Event\ObserverDeclaration;
use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Framework\Plugin\PluginDeclaration;
use ReflectionClass;

/**
 * What one module declares into the container.
 */
final class Wiring {
	/** @var array<string, Closure(Container): object> */
	private array $services = array();

	/** @var array<class-string, class-string> contract => interceptor */
	private array $contracts = array();

	/** @var list<PluginDeclaration> */
	private array $plugins = array();

	/** @var list<ObserverDeclaration> */
	private array $observers = array();

	public function __construct(private readonly string $module) {}

	/**
	 * @param Closure(Container): object $factory builds the service, resolving its constructor arguments from the container
	 */
	public function service(string $id, Closure $factory): void {
		if (preg_match('/\\\\Api\\\\/', $id) === 1 && interface_exists($id))
			throw new ModuleException(sprintf('Module %s wires %s as a plain service; an Api interface is wired with contract()', $this->module, $id));

		$this->add($id, $factory);
	}

	/**
	 * Wires a service contract: an interface directly in this module's Api
	 * namespace, the subject behind it, and the interceptor its plugins run in.
	 *
	 * @param class-string $contract
	 * @param class-string $interceptor a final class implementing $contract, constructed from ($contract $subject, PluginChain $plugins)
	 * @param Closure(Container): object $factory builds the subject
	 */
	public function contract(string $contract, string $interceptor, Closure $factory): void {
		if (!interface_exists($contract) || preg_match('/(?:^|\\\\)'.preg_quote($this->module, '/').'\\\\Api\\\\\w+$/', $contract) !== 1)
			throw new ModuleException(sprintf('Module %s wires contract %s, which is not an interface in its Api namespace', $this->module, $contract));

		if (!class_exists($interceptor) || !self::intercepts($interceptor, $contract))
			throw new ModuleException(sprintf('Module %s wires %s as the interceptor of %s; it must be a final class implementing it, constructed from (%s $subject, %s $plugins)', $this->module, $interceptor, $contract, $contract, PluginChain::class));

		$this->add($contract, $factory);
		$this->contracts[$contract] = $interceptor;
	}

	/**
	 * Plugs a service contract with a class of before<Method> and after<Method> methods.
	 *
	 * @param class-string $contract
	 * @param class-string $plugin
	 * @param Closure(Container): object $factory builds the plugin, resolving its constructor arguments from the container
	 */
	public function plugin(string $contract, string $plugin, Closure $factory): void {
		$this->plugins[] = new PluginDeclaration($this->module, $contract, $plugin, $factory);
	}

	/**
	 * Binds an observer to an event: a class declaring observe(<event> $event): void,
	 * run on every dispatch of that event.
	 *
	 * @param class-string $event a final class implementing EventInterface
	 * @param class-string $observer
	 * @param Closure(Container): object $factory builds the observer, resolving its constructor arguments from the container
	 */
	public function observer(string $event, string $observer, Closure $factory): void {
		if (!is_a($event, EventInterface::class, true) || !(new ReflectionClass($event))->isFinal())
			throw new ModuleException(sprintf('Module %s observes %s, which is not a final class implementing %s', $this->module, $event, EventInterface::class));

		if (!class_exists($observer) || !self::observes($observer, $event))
			throw new ModuleException(sprintf('Module %s binds %s to %s; it must declare observe(%s $event): void', $this->module, $observer, $event, $event));

		$this->observers[] = new ObserverDeclaration($this->module, $event, $observer, $factory);
	}

	/** @return array<string, Closure(Container): object> */
	public function services(): array {
		return $this->services;
	}

	/** @return array<class-string, class-string> contract => interceptor */
	public function contracts(): array {
		return $this->contracts;
	}

	/** @return list<PluginDeclaration> in declaration order */
	public function plugins(): array {
		return $this->plugins;
	}

	/** @return list<ObserverDeclaration> in declaration order */
	public function observers(): array {
		return $this->observers;
	}

	/** @param Closure(Container): object $factory */
	private function add(string $id, Closure $factory): void {
		if (isset($this->services[$id]))
			throw new ModuleException(sprintf('Module %s wires service "%s" twice', $this->module, $id));

		$this->services[$id] = $factory;
	}

	/**
	 * @param class-string $interceptor
	 * @param class-string $contract
	 */
	private static function intercepts(string $interceptor, string $contract): bool {
		$class = new ReflectionClass($interceptor);
		$parameters = $class->getConstructor()?->getParameters() ?? array();

		return $class->isFinal()
			&& $class->implementsInterface($contract)
			&& count($parameters) === 2
			&& (string) $parameters[0]->getType() === $contract
			&& (string) $parameters[1]->getType() === PluginChain::class;
	}

	/**
	 * @param class-string $observer
	 * @param class-string $event
	 */
	private static function observes(string $observer, string $event): bool {
		$class = new ReflectionClass($observer);
		if (!$class->hasMethod('observe'))
			return false;

		$method = $class->getMethod('observe');
		$parameters = $method->getParameters();

		return $method->isPublic()
			&& !$method->isStatic()
			&& count($parameters) === 1
			&& (string) $parameters[0]->getType() === $event
			&& (string) $method->getReturnType() === 'void';
	}
}
