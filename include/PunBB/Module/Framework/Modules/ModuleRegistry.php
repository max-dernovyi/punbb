<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Modules;

use Closure;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Plugin\PluginManager;
use PunBB\Module\Framework\Routing\Route;
use PunBB\Module\Framework\Routing\Router;
use Throwable;

/**
 * The registered modules in load order: every dependency, and every present
 * loadAfter() module, before the module naming it; otherwise registration order.
 *
 * A core module's defect is an exception. A third-party module that does not
 * load, declare, order or wire is skipped instead, with every module depending
 * on it, and the reason kept in skipped(): one broken add-on leaves the board up.
 * A class PHP cannot compile against ModuleInterface is a fatal error no catch reaches.
 */
final class ModuleRegistry {
	/** At most 50 characters, as the modules table records a name and a version. */
	private const NAME_PATTERN = '/^[A-Z][A-Za-z0-9]{0,49}$/D';

	/** Numbers separated by dots, not all of them zero: zero is what a board records for a module it has never had. */
	private const VERSION_PATTERN = '/^(?=.{1,50}$)(?=.*[1-9])\d+(\.\d+)*$/D';

	/** @var array<string, ModuleInterface> */
	private array $modules;

	/** @var array<string, string> module => why it is not registered, in the order it was skipped */
	private array $skipped = array();

	/** @var list<string> the third-party modules registered, in load order */
	private array $thirdParty = array();

	/** @var array<string, Wiring>|null module => what it declared, each module wired once */
	private ?array $wirings = null;

	/** Registers the forum's own modules; a defect in any of them is an exception. */
	public function __construct(ModuleInterface ...$modules) {
		$this->modules = self::resolve($modules);
	}

	/**
	 * Registers $core as the constructor does, then each of $thirdParty that
	 * loads, after them. A core module naming a third-party one is an exception.
	 *
	 * @param list<ModuleInterface> $core
	 * @param list<ModuleInterface> $thirdParty
	 * @param array<string, string> $unloadable module => why its class did not load
	 */
	public static function withThirdParty(array $core, array $thirdParty, array $unloadable = array()): self {
		$coreNames = array();
		foreach ($core as $module)
			$coreNames[$module->name()] = true;

		$skipped = $unloadable;
		$declared = self::declarations($thirdParty, $coreNames, $skipped);
		self::refuseThirdPartyPredecessors($core, $coreNames, array_merge(array_keys($declared), array_keys($skipped)));

		$registry = new self(...$core);
		if ($declared !== array())
		{
			$registry->admit($declared, $skipped);
			$registry->wireAll($declared, $skipped);
		}

		$registry->skipped = $skipped;
		$registry->thirdParty = array_values(array_filter($registry->names(), static fn (string $name): bool => isset($declared[$name])));

		return $registry;
	}

	/**
	 * Registers every <Name>/Module.php of each tree as class <namespace><Name>\Module,
	 * the core trees' modules first.
	 */
	public static function discover(ModuleTree ...$trees): self {
		$core = array();
		$thirdParty = array();
		$unloadable = array();

		foreach ($trees as $tree)
		{
			$files = glob(rtrim($tree->directory, '/').'/*/Module.php');
			if ($files === false)
				throw new ModuleException(sprintf('Cannot read the module directory %s', $tree->directory));

			sort($files);

			foreach ($files as $file)
			{
				$name = basename(dirname($file));

				if ($tree->core)
				{
					$core[] = self::load($file, $name, $tree->namespace);
					continue;
				}

				try {
					$thirdParty[] = self::load($file, $name, $tree->namespace);
				}
				catch (Throwable $e) {
					$unloadable[$name] = self::failure($name, $e);
				}
			}
		}

		return self::withThirdParty($core, $thirdParty, $unloadable);
	}

	/**
	 * The forum's modules: its own, then the third-party ones installed below
	 * $root. Each one skipped is handed to $report, one line naming it and why.
	 *
	 * @param (Closure(string): mixed)|null $report
	 */
	public static function forum(string $root, ?Closure $report = null): self {
		$registry = self::discover(ModuleTree::core($root), ModuleTree::installed($root));

		if ($report !== null)
			foreach ($registry->skipped as $reason)
				$report('PunBB module skipped: '.$reason);

		return $registry;
	}

	/** @return list<string> */
	public function names(): array {
		return array_keys($this->modules);
	}

	/** @return list<ModuleInterface> */
	public function modules(): array {
		return array_values($this->modules);
	}

	/** @return array<string, string> module => why it is not registered: a third-party module that failed, or one depending on it */
	public function skipped(): array {
		return $this->skipped;
	}

	/** @return list<string> the third-party modules registered, in load order */
	public function thirdParty(): array {
		return $this->thirdParty;
	}

	/**
	 * Assembles the container from every module's wiring, in load order. A
	 * contract resolves to its interceptor when any module plugs it, the event
	 * dispatcher holds every module's observers, and this registry is a service
	 * for what reads the modules' declarations.
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

		if (isset($owners[self::class]))
			throw new ModuleException(sprintf('Module %s wires service "%s", which is the registry itself', $owners[self::class], self::class));

		$factories[EventDispatcher::class] = static fn (Container $container): object => new EventDispatcher($observers, $container);
		$factories[self::class] = fn (): object => $this;

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
			$wirings[$name] = self::wire($name, $module);

		return $this->wirings = $wirings;
	}

	private static function wire(string $name, ModuleInterface $module): Wiring {
		$wiring = new Wiring($name);
		$module->wire($wiring);

		return $wiring;
	}

	private static function load(string $file, string $name, string $namespace): ModuleInterface {
		$class = $namespace.$name.'\\Module';

		// A file included before without declaring the class is not included again: a second discovery would redeclare what it did declare
		$autoload = !in_array(realpath($file), get_included_files(), true);
		if (!class_exists($class, $autoload) || !is_a($class, ModuleInterface::class, true))
			throw new ModuleException(sprintf('%s does not declare a module class %s', $file, $class));

		$module = new $class();
		if ($module->name() !== $name)
			throw new ModuleException(sprintf('%s is named %s, not after its directory', $class, $module->name()));

		return $module;
	}

	private static function failure(string $name, Throwable $e): string {
		if ($e instanceof ModuleException)
			return $e->getMessage();

		return sprintf('Module %s throws %s: %s in %s on line %d', $name, $e::class, $e->getMessage(), $e->getFile(), $e->getLine());
	}

	/** @throws ModuleException */
	private static function check(string $name, string $version): void {
		if (preg_match(self::NAME_PATTERN, $name) !== 1)
			throw new ModuleException(sprintf('Module name "%s" is not a namespace segment', $name));

		if (preg_match(self::VERSION_PATTERN, $version) !== 1)
			throw new ModuleException(sprintf('Module %s declares version "%s"; a version is numbers separated by dots, above zero', $name, $version));
	}

	/**
	 * @param array<mixed> $names
	 * @return list<string>
	 * @throws ModuleException
	 */
	private static function moduleNames(string $name, string $method, array $names): array {
		foreach ($names as $other)
			if (!is_string($other))
				throw new ModuleException(sprintf('Module %s lists %s in %s(); it lists module names', $name, get_debug_type($other), $method));

		return array_values($names);
	}

	/**
	 * What each third-party module declares of itself, read once. One that
	 * throws, is misnamed or misversioned, or takes a registered name is skipped.
	 *
	 * @param list<ModuleInterface> $thirdParty
	 * @param array<string, true> $core
	 * @param array<string, string> $skipped
	 * @return array<string, array{ModuleInterface, list<string>, list<string>}> module => it, its dependencies, what it loads after
	 */
	private static function declarations(array $thirdParty, array $core, array &$skipped): array {
		$declared = array();

		foreach ($thirdParty as $module)
		{
			$name = $module::class;

			try {
				$name = $module->name();
				self::check($name, $module->version());
				$declaration = array($module, self::moduleNames($name, 'dependencies', $module->dependencies()), self::moduleNames($name, 'loadAfter', $module->loadAfter()));
			}
			catch (Throwable $e) {
				$skipped[$name] = self::failure($name, $e);
				continue;
			}

			if (isset($core[$name]) || isset($declared[$name]))
				$skipped[$name] = sprintf('Module %s is registered twice', $name);
			else
				$declared[$name] = $declaration;
		}

		return $declared;
	}

	/**
	 * A core module never depends on, or loads after, a third-party one.
	 *
	 * @param list<ModuleInterface> $core
	 * @param array<string, true> $coreNames
	 * @param list<string> $thirdParty the names of the third-party modules, registered or skipped
	 */
	private static function refuseThirdPartyPredecessors(array $core, array $coreNames, array $thirdParty): void {
		foreach ($core as $module)
		{
			foreach ($module->dependencies() as $dependency)
				if (!isset($coreNames[$dependency]) && in_array($dependency, $thirdParty, true))
					throw new ModuleException(sprintf('Module %s depends on %s, a third-party module; a core module depends on core modules only', $module->name(), $dependency));

			foreach ($module->loadAfter() as $predecessor)
				if (!isset($coreNames[$predecessor]) && in_array($predecessor, $thirdParty, true))
					throw new ModuleException(sprintf('Module %s loads after %s, a third-party module; a core module is ordered against core modules only', $module->name(), $predecessor));
		}
	}

	/**
	 * Orders the third-party modules after the core ones. One depending on a
	 * module that is neither registered nor pending is skipped, and so is every
	 * module of a load-order cycle.
	 *
	 * @param array<string, array{ModuleInterface, list<string>, list<string>}> $pending
	 * @param array<string, string> $skipped
	 */
	private function admit(array $pending, array &$skipped): void {
		while ($pending !== array())
		{
			foreach ($pending as $name => [, $dependencies])
			{
				foreach ($dependencies as $dependency)
				{
					if (!isset($this->modules[$dependency]) && !isset($pending[$dependency]))
					{
						$skipped[$name] = sprintf(isset($skipped[$dependency]) ? 'Module %s depends on %s, which is skipped' : 'Module %s depends on %s, which is not registered', $name, $dependency);
						unset($pending[$name]);

						// Skipping it may leave another pending module without its dependency
						continue 3;
					}
				}
			}

			$predecessors = array();
			foreach ($pending as $name => [$module, $dependencies, $loadAfter])
			{
				$predecessors[$name] = array_values(array_filter(array_merge($dependencies, $loadAfter), static fn (string $predecessor): bool => isset($pending[$predecessor])));

				if ($predecessors[$name] === array())
				{
					$this->modules[$name] = $module;
					unset($pending[$name]);
					continue 2;
				}
			}

			// Every pending module waits on another pending one
			$cycle = self::cycle($predecessors, array());
			foreach ($cycle as $name)
			{
				$skipped[$name] = 'Module load order has a cycle: '.implode(' -> ', $cycle);
				unset($pending[$name]);
			}
		}
	}

	/**
	 * Wires every module now rather than on first use, so that a third-party
	 * module whose wiring throws, or collides with a module before it, is
	 * skipped along with every module depending on it.
	 *
	 * @param array<string, array{ModuleInterface, list<string>, list<string>}> $thirdParty
	 * @param array<string, string> $skipped
	 */
	private function wireAll(array $thirdParty, array &$skipped): void {
		$wirings = array();
		$owners = array();
		$routes = array();
		$interceptors = array();

		foreach ($this->modules as $name => $module)
		{
			if (!isset($thirdParty[$name]))
				$wiring = self::wire($name, $module);
			else
			{
				foreach ($thirdParty[$name][1] as $dependency)
				{
					if (!isset($wirings[$dependency]))
					{
						$skipped[$name] = sprintf('Module %s depends on %s, which is skipped', $name, $dependency);
						unset($this->modules[$name]);
						continue 2;
					}
				}

				try {
					$wiring = self::wire($name, $module);
					self::admissible($name, $wiring, $owners, $routes, $interceptors);
				}
				catch (Throwable $e) {
					$skipped[$name] = self::failure($name, $e);
					unset($this->modules[$name]);
					continue;
				}
			}

			foreach (array_keys($wiring->services()) as $id)
				$owners[$id] ??= $name;

			$routes = array_merge($routes, $wiring->routes());
			$interceptors += $wiring->contracts();
			$wirings[$name] = $wiring;
		}

		$this->wirings = $wirings;
	}

	/**
	 * What container() and router() would refuse of $wiring on top of the
	 * modules wired before it.
	 *
	 * @param array<string, string> $owners service => the module wiring it
	 * @param list<Route> $routes
	 * @param array<class-string, class-string> $interceptors contract => interceptor
	 * @throws ModuleException
	 */
	private static function admissible(string $name, Wiring $wiring, array $owners, array $routes, array $interceptors): void {
		foreach (array_keys($wiring->services()) as $id)
		{
			if ($id === EventDispatcher::class || $id === self::class)
				throw new ModuleException(sprintf('Module %s wires service "%s", which the registry provides', $name, $id));

			if (isset($owners[$id]))
				throw new ModuleException(sprintf('Modules %s and %s both wire service "%s"', $owners[$id], $name, $id));
		}

		new Router(array_merge($routes, $wiring->routes()));
		new PluginManager($interceptors + $wiring->contracts(), $wiring->plugins());
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
			self::check($name, $module->version());

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
