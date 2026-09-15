<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;

/**
 * The scope a page script ran extension code in: global. A point sees every
 * global by name, with the variables the bridge hands it over them, and what
 * the code creates stays a global for the points after it — a variable set at
 * a page's start is still there at its end.
 */
final class PageScope {
	public function __construct(private readonly PointEvaluator $points) {}

	/**
	 * Runs a point no event or plugin covers, by name, where the bridge reaches it.
	 *
	 * @param array<mixed> $locals variable name => reference
	 */
	public function run(string $point, array $locals = array()): mixed {
		if (!LegacyScope::attached($point))
			return null;

		return $this->points->run($point, LegacyScope::with($locals), null, self::keep(...));
	}

	/**
	 * Runs a point where the event covering it is observed.
	 *
	 * @param array<mixed> $locals variable name => reference
	 */
	public function observe(string $point, EventInterface $event, array $locals = array()): mixed {
		if (!LegacyScope::attached($point))
			return null;

		return $this->points->run($point, LegacyScope::with($locals), $event, self::keep(...));
	}

	/**
	 * Renders a markup point where the event covering it is observed, and hands back what it emitted.
	 *
	 * @param array<mixed> $locals variable name => reference
	 */
	public function renderObserved(string $point, EventInterface $event, array $locals = array()): string {
		if (!LegacyScope::attached($point))
			return '';

		return $this->points->render($point, LegacyScope::with($locals), $event, self::keep(...));
	}

	/**
	 * Runs a point from the plugin on the contract method covering it.
	 *
	 * @param array<mixed> $locals variable name => reference
	 */
	public function plugged(string $point, string $method, array $locals = array()): mixed {
		if (!LegacyScope::attached($point))
			return null;

		return $this->points->runPlugged($point, LegacyScope::with($locals), $method, self::keep(...));
	}

	/** @param array<string, mixed> $created */
	private static function keep(array $created): void {
		foreach ($created as $name => $value)
			$GLOBALS[$name] = $value;
	}
}
