<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge;

use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\LegacyBridge\Hook\HookMap;
use PunBB\Module\LegacyBridge\Hook\MarkupHookRunner;
use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Hook\StatementHookRunner;

/**
 * The v2.0 bridge to extension code stored for eval($hook). Nothing else in
 * the core references it, so v2.1 deletes it whole.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'LegacyBridge';
	}

	public function dependencies(): array {
		return array('Framework');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->service(HookMap::class, fn (): object => new HookMap());
		$wiring->service(StatementHookRunner::class, fn (Container $c): object => new StatementHookRunner(self::points($c)));
		$wiring->service(MarkupHookRunner::class, fn (Container $c): object => new MarkupHookRunner(self::points($c)));
	}

	/** Not a service of its own, so nothing outside the bridge reaches a point past the runners' markers. */
	private static function points(Container $c): PointEvaluator {
		return new PointEvaluator($c->get(HookMap::class), self::storedCode(...));
	}

	/** What the hooks cache holds for a point, read through get_hook() so FORUM_DISABLE_HOOKS still holds. */
	private static function storedCode(string $point): string {
		$code = \get_hook($point);

		return is_string($code) ? $code : '';
	}
}
