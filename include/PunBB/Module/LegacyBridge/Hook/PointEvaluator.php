<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Hook;

use Closure;
use PunBB\Module\Framework\Event\EventInterface;
use ReflectionReference;
use Throwable;

/**
 * Runs the code stored at a legacy point as `$return = ($hook = get_hook('<id>')) ? eval($hook) : null;`
 * does at its site, for both runners, and for the bridge's own observers and
 * template protocol, which run points where the layout reaches them. Outside
 * the bridge a point is reached through a runner, whose marker fires.
 *
 * The point exposes its variables by reference. The stored code sees each as a
 * local holding a plain PHP value, and whatever it changed is written back to
 * the caller, also when it returns or throws. A return ends the point: later
 * extensions there never run and the returning one's ext_info_stack pop is skipped.
 *
 * A point the map covers runs only where the event covering it is dispatched,
 * from that event's observer.
 */
final class PointEvaluator {
	/** Names a hook cannot receive: evaluate()'s own locals and what PHP refuses to extract. */
	private const RESERVED = array('this', 'GLOBALS', 'hook', 'exposed');

	/** @var array<string, mixed>|null the locals of the last evaluate() as it ended, set also on a throw */
	private static ?array $locals = null;

	/** @param Closure(string): string $storedCode the code attached to a point, '' when nothing is */
	public function __construct(private readonly HookMap $map, private readonly Closure $storedCode) {}

	/**
	 * @param array<mixed> $exposed variable name => reference to the caller's variable
	 * @param ?EventInterface $event the event being observed, when the point runs where its covering event is dispatched
	 * @param ?Closure(array<string, mixed>): void $created receives the variables the stored code created, by name
	 * @return mixed what the stored code returned, null when it did not return
	 */
	public function run(string $point, array $exposed, ?EventInterface $event = null, ?Closure $created = null): mixed {
		$covering = $this->map->covering($point);
		if ($covering !== array() && ($event === null || !in_array($event::class, $covering, true)))
			throw new HookException(sprintf('Legacy point %s is covered by %s, where its stored code runs', $point, implode(' and ', $covering)));

		if ($covering === array() && $event !== null)
			throw new HookException(sprintf('Legacy point %s is not covered by %s, so its stored code does not run there', $point, $event::class));

		return $this->evaluatePoint($point, $exposed, $created);
	}

	/**
	 * Runs a point a plugin on a contract method covers, from that plugin.
	 *
	 * @param array<mixed> $exposed variable name => reference to the caller's variable
	 * @param string $method the covering <Api contract>::<method>
	 * @param ?Closure(array<string, mixed>): void $created receives the variables the stored code created, by name
	 * @return mixed what the stored code returned, null when it did not return
	 */
	public function runPlugged(string $point, array $exposed, string $method, ?Closure $created = null): mixed {
		$covering = $this->map->covering($point);
		if (!in_array($method, $covering, true))
			throw new HookException(sprintf('Legacy point %s is covered by %s, not by %s', $point, $covering !== array() ? implode(' and ', $covering) : 'nothing', $method));

		return $this->evaluatePoint($point, $exposed, $created);
	}

	/**
	 * Runs a markup point in an output buffer of its own and hands back what it
	 * emitted; a return is discarded, a throw flushes the partial output.
	 *
	 * @param array<mixed> $exposed variable name => reference to the template's variable
	 * @param ?EventInterface $event the event being observed, when the point runs where its covering event is dispatched
	 * @param ?Closure(array<string, mixed>): void $created receives the variables the stored code created, by name
	 */
	public function render(string $point, array $exposed, ?EventInterface $event = null, ?Closure $created = null): string {
		ob_start();

		try {
			$this->run($point, $exposed, $event, $created);
		}
		catch (Throwable $e) {
			// What was emitted before the throw stays where the legacy site would have left it.
			ob_end_flush();
			throw $e;
		}

		return (string) ob_get_clean();
	}

	/**
	 * @param array<mixed> $exposed
	 * @param ?Closure(array<string, mixed>): void $created
	 */
	private function evaluatePoint(string $point, array $exposed, ?Closure $created): mixed {
		$values = self::values($point, $exposed);

		$code = ($this->storedCode)($point);
		if ($code === '')
			return null;

		$before = $values;
		self::$locals = null;

		try {
			$returned = self::evaluate($code, $values);
		}
		finally {
			// Read from the scope, not $values: unset() then reassignment detaches the local from it.
			$after = self::takeLocals() ?? $values;

			// Each element of $exposed is a reference: assigning it assigns the caller's variable.
			foreach ($before as $name => $value)
			{
				if (!array_key_exists($name, $after))
					$exposed[$name] = null;
				else if ($after[$name] !== $value)
					$exposed[$name] = $after[$name];
			}

			if ($created !== null)
				$created(array_diff_key($after, $before, array_flip(self::RESERVED)));
		}

		return $returned;
	}

	/** @return array<string, mixed>|null */
	private static function takeLocals(): ?array {
		$locals = self::$locals;
		self::$locals = null;

		return $locals;
	}

	/**
	 * @param array<mixed> $exposed
	 * @return array<string, mixed> a plain copy of every exposed value
	 */
	private static function values(string $point, array $exposed): array {
		$values = array();
		foreach ($exposed as $name => $value)
		{
			if (!is_string($name) || preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/', $name) !== 1 || in_array($name, self::RESERVED, true))
				throw new HookException(sprintf('Legacy point %s exposes "%s", which a hook cannot receive as a variable', $point, $name));

			if (ReflectionReference::fromArrayElement($exposed, $name) === null)
				throw new HookException(sprintf('Legacy point %s exposes $%s by value; a change to it would not reach the caller', $point, $name));

			$values[$name] = $value;
		}

		return $values;
	}

	/**
	 * Every local here is visible to the stored code, as at a legacy site where
	 * $hook holds it. The exposed values are bound by reference, so a change
	 * survives an exception.
	 *
	 * @param array<string, mixed> $exposed
	 * @return mixed what the code returned
	 */
	private static function evaluate(string $hook, array &$exposed): mixed {
		extract($exposed, EXTR_REFS);
		unset($exposed);

		try {
			return eval($hook);
		}
		finally {
			// A static, not a local: the stored code would see and could clobber a local.
			self::$locals = get_defined_vars();
		}
	}
}
