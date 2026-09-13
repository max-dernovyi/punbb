<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Plugin;

use Closure;
use PunBB\Module\Framework\Container\Container;

/**
 * One plugin a module wires onto a service contract.
 */
final readonly class PluginDeclaration {
	/**
	 * @param class-string $contract
	 * @param class-string $class
	 * @param Closure(Container): object $factory builds the plugin, resolving its constructor arguments from the container
	 */
	public function __construct(
		public string $module,
		public string $contract,
		public string $class,
		public Closure $factory
	) {}
}
