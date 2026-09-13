<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Plugin;

use Closure;

/**
 * The plugins on one contract instance, run by its interceptor around every call.
 */
final class PluginChain {
	/** @param list<object> $plugins in module order, then declaration order */
	public function __construct(private readonly array $plugins) {}

	/**
	 * Runs every before<Method>, the subject, then every after<Method>, in plugin
	 * order. A before may replace the arguments and an after the result; neither
	 * can skip the call.
	 *
	 * The never-typed parameters accept the subject's method of up to eight
	 * parameters as a first-class callable, keeping its return type.
	 *
	 * @template T
	 * @param object $contract the interceptor, handed to each plugin as its subject
	 * @param array<mixed> $arguments
	 * @param Closure(never, never, never, never, never, never, never, never): T $proceed
	 * @return T
	 */
	public function call(object $contract, string $method, array $arguments, Closure $proceed): mixed {
		$arguments = array_values($arguments);
		$suffix = ucfirst($method);

		foreach ($this->plugins as $plugin)
		{
			if (!method_exists($plugin, 'before'.$suffix))
				continue;

			$rewritten = $plugin->{'before'.$suffix}($contract, ...$arguments);
			if ($rewritten === null)
				continue;

			if (!is_array($rewritten) || !array_is_list($rewritten))
				throw new PluginException(sprintf('%s::before%s returned neither null nor a list of arguments', $plugin::class, $suffix));

			$arguments = $rewritten;
		}

		$result = call_user_func_array($proceed, $arguments);

		foreach ($this->plugins as $plugin)
			if (method_exists($plugin, 'after'.$suffix))
				$result = $plugin->{'after'.$suffix}($contract, $result, ...$arguments);

		/** @var T $result */
		return $result;
	}
}
