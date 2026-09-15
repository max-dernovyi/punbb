<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout\Observer;

use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\Layout\Event\DebugRendering;

/**
 * Renders the ft_debug_* points at their positions in the debug footer, in a
 * buffer of their own as the markup-hook runner renders a point.
 */
final class DebugObserver {
	public const POINTS = array(
		DebugRendering::START	=> 'ft_debug_output_start',
		DebugRendering::END		=> 'ft_debug_end',
	);

	public function __construct(private readonly PointEvaluator $points) {}

	public function observe(DebugRendering $event): void {
		if (!LegacyScope::attached(self::POINTS[$event->position()]))
			return;

		$event->append($this->points->render(self::POINTS[$event->position()], LegacyScope::with(array()), $event));
	}
}
