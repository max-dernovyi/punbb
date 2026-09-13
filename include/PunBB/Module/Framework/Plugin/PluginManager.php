<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Plugin;

use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Puts the plugins the modules declared in front of the contracts they plug.
 * Every declaration is checked here, so a wrong plugin fails the container
 * assembly, not a request.
 */
final class PluginManager {
	/** @var array<string, list<PluginDeclaration>> contract => its plugins, in module order */
	private readonly array $plugins;

	/**
	 * @param array<class-string, class-string> $interceptors contract => interceptor, as wired
	 * @param list<PluginDeclaration> $plugins in module order, then declaration order
	 */
	public function __construct(private readonly array $interceptors, array $plugins) {
		$byContract = array();
		foreach ($plugins as $plugin)
		{
			if (!isset($interceptors[$plugin->contract]))
				throw new ModuleException(sprintf('Module %s plugs %s, which no module wires as a contract', $plugin->module, $plugin->contract));

			self::check($plugin);
			$byContract[$plugin->contract][] = $plugin;
		}

		$this->plugins = $byContract;
	}

	/** The subject itself when nothing plugs $contract, otherwise its interceptor. */
	public function intercept(string $contract, object $subject, Container $container): object {
		if (!isset($this->plugins[$contract], $this->interceptors[$contract]))
			return $subject;

		$instances = array();
		foreach ($this->plugins[$contract] as $plugin)
		{
			$instance = ($plugin->factory)($container);
			if (!$instance instanceof $plugin->class)
				throw new ModuleException(sprintf('Module %s wired plugin %s to a %s', $plugin->module, $plugin->class, $instance::class));

			$instances[] = $instance;
		}

		$interceptor = $this->interceptors[$contract];

		return new $interceptor($subject, new PluginChain($instances));
	}

	private static function check(PluginDeclaration $plugin): void {
		if (!class_exists($plugin->class))
			throw new ModuleException(sprintf('Module %s plugs %s with %s, which is not a class', $plugin->module, $plugin->contract, $plugin->class));

		$contract = new ReflectionClass($plugin->contract);
		$plugs = 0;

		foreach ((new ReflectionClass($plugin->class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
		{
			if ($method->isConstructor())
				continue;

			$name = $method->getName();

			if (stripos($name, 'around') === 0)
				throw new ModuleException(sprintf('Module %s declares %s::%s; a plugin is before and after only', $plugin->module, $plugin->class, $name));

			if (preg_match('/^(before|after)([A-Z]\w*)$/', $name, $match) !== 1 || $method->isStatic()
				|| !$contract->hasMethod($match[2]) || ucfirst($contract->getMethod($match[2])->getName()) !== $match[2])
				throw new ModuleException(sprintf('%s::%s plugs no method of %s', $plugin->class, $name, $plugin->contract));

			self::checkSignature($plugin, $method, $match[1] === 'after', $contract->getMethod($match[2]));
			$plugs++;
		}

		if ($plugs === 0)
			throw new ModuleException(sprintf('Module %s plugs %s with %s, which declares no before or after method', $plugin->module, $plugin->contract, $plugin->class));
	}

	/**
	 * before<Method>(Contract $subject, ...arguments): ?array
	 * after<Method>(Contract $subject, <return> $result, ...arguments): <return>
	 * Trailing arguments may be left off; a void method's result is null.
	 */
	private static function checkSignature(PluginDeclaration $plugin, ReflectionMethod $method, bool $after, ReflectionMethod $target): void {
		$returns = (string) $target->getReturnType();
		if ($returns === 'void')
			$returns = 'null';

		$expected = array($plugin->contract);
		if ($after)
			$expected[] = $returns;

		foreach ($target->getParameters() as $parameter)
			$expected[] = (string) $parameter->getType();

		$declared = array_map(static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(), $method->getParameters());

		if (count($declared) < ($after ? 2 : 1) || $declared !== array_slice($expected, 0, count($declared)))
			throw new ModuleException(sprintf('%s::%s must take (%s)', $plugin->class, $method->getName(), implode(', ', $expected)));

		// The interceptor forwards func_get_args(), which omits a default the caller did not pass, so the plugin must supply the same one.
		$offset = $after ? 2 : 1;
		foreach ($target->getParameters() as $position => $parameter)
		{
			$own = $method->getParameters()[$position + $offset] ?? null;
			if ($own === null || !$parameter->isDefaultValueAvailable())
				continue;

			if (!$own->isDefaultValueAvailable() || $own->getDefaultValue() !== $parameter->getDefaultValue())
				throw new ModuleException(sprintf('%s::%s must give $%s the default %s::%s gives it', $plugin->class, $method->getName(), $own->getName(), $plugin->contract, $target->getName()));
		}

		$expectedReturn = $after ? $returns : '?array';
		if ((string) $method->getReturnType() !== $expectedReturn)
			throw new ModuleException(sprintf('%s::%s must return %s', $plugin->class, $method->getName(), $expectedReturn));
	}
}
