<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Modules;

use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Plugin\PluginManager;
use PunBB\Module\Framework\Routing\Router;

/**
 * The registered modules in load order: every dependency, and every present
 * loadAfter() module, before the module naming it; otherwise registration order.
 */
final class ModuleRegistry {
	private const NAME_PATTERN = '/^[A-Z][A-Za-z0-9]*$/';

	/** @var array<string, ModuleInterface> */
	private readonly array $modules;

	/** @var array<string, Wiring>|null module => what it declared, each module wired once */
	private ?array $wirings = null;

	public function __construct(ModuleInterface ...$modules) {
		$this->modules = self::resolve($modules);
	}

	/**
	 * Registers every <Name>/Module.php below $directory as class $namespace<Name>\Module,
	 * after $registered, the modules of another tree.
	 */
	public static function discover(string $directory, string $namespace, ModuleInterface ...$registered): self {
		$files = glob(rtrim($directory, '/').'/*/Module.php');
		if ($files === false)
			throw new ModuleException(sprintf('Cannot read the module directory %s', $directory));

		sort($files);

		$modules = $registered;
		foreach ($files as $file)
		{
			$name = basename(dirname($file));
			$class = $namespace.$name.'\\Module';

			if (!is_a($class, ModuleInterface::class, true))
				throw new ModuleException(sprintf('%s does not declare a module class %s', $file, $class));

			$module = new $class();
			if ($module->name() !== $name)
				throw new ModuleException(sprintf('%s is named %s, not after its directory', $class, $module->name()));

			$modules[] = $module;
		}

		return new self(...$modules);
	}

	/** @return list<string> */
	public function names(): array {
		return array_keys($this->modules);
	}

	/** @return list<ModuleInterface> */
	public function modules(): array {
		return array_values($this->modules);
	}

	/**
	 * Assembles the container from every module's wiring, in load order. A
	 * contract resolves to its interceptor when any module plugs it, and the
	 * event dispatcher holds every module's observers.
	 */
	public function container(): Container {
		$factories = array();
		$owners = array();
		$interceptors = array();
		$plugins = array();
		$observers = array();

		foreach ($this->wirings() as $name => $wiring)
		{
			foreach ($wiring->services() as $id => $factory)
			{
				if (isset($owners[$id]))
					throw new ModuleException(sprintf('Modules %s and %s both wire service "%s"', $owners[$id], $name, $id));

				$owners[$id] = $name;
				$factories[$id] = $factory;
			}

			$interceptors += $wiring->contracts();
			$plugins = array_merge($plugins, $wiring->plugins());
			$observers = array_merge($observers, $wiring->observers());
		}

		if (isset($owners[EventDispatcher::class]))
			throw new ModuleException(sprintf('Module %s wires service "%s", which the registry assembles from every module\'s observers', $owners[EventDispatcher::class], EventDispatcher::class));

		$factories[EventDispatcher::class] = static fn (Container $container): object => new EventDispatcher($observers, $container);

		$manager = new PluginManager($interceptors, $plugins);
		foreach (array_keys($interceptors) as $contract)
		{
			$subject = $factories[$contract];
			$factories[$contract] = static fn (Container $container): object => $manager->intercept($contract, $subject($container), $container);
		}

		return new Container($factories);
	}

	/**
	 * The route map from every module's routes, in load order. It needs no
	 * container, so a request is routed before the forum is booted for it.
	 */
	public function router(): Router {
		$routes = array();
		foreach ($this->wirings() as $wiring)
			$routes = array_merge($routes, $wiring->routes());

		return new Router($routes);
	}

	/** @return array<string, Wiring> */
	private function wirings(): array {
		if ($this->wirings !== null)
			return $this->wirings;

		$wirings = array();
		foreach ($this->modules as $name => $module)
		{
			$wiring = new Wiring($name);
			$module->wire($wiring);
			$wirings[$name] = $wiring;
		}

		return $this->wirings = $wirings;
	}

	/**
	 * @param array<ModuleInterface> $modules
	 * @return array<string, ModuleInterface>
	 */
	private static function resolve(array $modules): array {
		$registered = array();
		foreach ($modules as $module)
		{
			$name = $module->name();

			if (preg_match(self::NAME_PATTERN, $name) !== 1)
				throw new ModuleException(sprintf('Module name "%s" is not a namespace segment', $name));

			if (isset($registered[$name]))
				throw new ModuleException(sprintf('Module %s is registered twice', $name));

			$registered[$name] = $module;
		}

		$predecessors = array();
		foreach ($registered as $name => $module)
		{
			$predecessors[$name] = array();

			foreach ($module->dependencies() as $dependency)
			{
				if (!isset($registered[$dependency]))
					throw new ModuleException(sprintf('Module %s depends on %s, which is not registered', $name, $dependency));

				$predecessors[$name][] = $dependency;
			}

			foreach ($module->loadAfter() as $predecessor)
				if (isset($registered[$predecessor]))
					$predecessors[$name][] = $predecessor;
		}

		$ordered = array();
		while (count($ordered) < count($registered))
		{
			$next = null;
			foreach ($predecessors as $name => $before)
			{
				if (!isset($ordered[$name]) && array_diff($before, array_keys($ordered)) === array())
				{
					$next = $name;
					break;
				}
			}

			if ($next === null)
				throw new ModuleException('Module load order has a cycle: '.implode(' -> ', self::cycle($predecessors, $ordered)));

			$ordered[$next] = $registered[$next];
		}

		return $ordered;
	}

	/**
	 * Walks predecessors among the unordered modules until one repeats. Each of
	 * them has an unordered predecessor, or it would have been ordered.
	 *
	 * @param array<string, list<string>> $predecessors
	 * @param array<string, ModuleInterface> $ordered
	 * @return list<string>
	 */
	private static function cycle(array $predecessors, array $ordered): array {
		$pending = array_diff_key($predecessors, $ordered);
		$name = (string) array_key_first($pending);
		$path = array();

		while (!in_array($name, $path, true))
		{
			$path[] = $name;
			foreach ($pending[$name] as $predecessor)
			{
				if (isset($pending[$predecessor]))
				{
					$name = $predecessor;
					break;
				}
			}
		}

		$cycle = array_slice($path, (int) array_search($name, $path, true));
		$cycle[] = $name;

		return $cycle;
	}
}
