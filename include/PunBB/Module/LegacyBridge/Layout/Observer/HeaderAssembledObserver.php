<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout\Observer;

use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\Layout\Event\HeaderAssembled;

/**
 * Runs hd_end.
 */
final class HeaderAssembledObserver {
	public function __construct(private readonly PointEvaluator $points) {}

	public function observe(HeaderAssembled $event): void {
		if (!LegacyScope::attached('hd_end'))
			return;

		$this->points->run('hd_end', LegacyScope::with(array()), $event);
	}
}
