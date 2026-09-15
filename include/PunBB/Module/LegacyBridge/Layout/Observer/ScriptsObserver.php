<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout\Observer;

use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\Layout\Event\ScriptsAssembling;

/**
 * Runs ft_js_include, where extension code registers its scripts with $forum_loader.
 */
final class ScriptsObserver {
	public function __construct(private readonly PointEvaluator $points) {}

	public function observe(ScriptsAssembling $event): void {
		if (!LegacyScope::attached('ft_js_include'))
			return;

		$this->points->run('ft_js_include', LegacyScope::with(array()), $event);
	}
}
