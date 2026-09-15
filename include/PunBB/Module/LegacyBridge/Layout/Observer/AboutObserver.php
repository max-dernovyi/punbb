<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout\Observer;

use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\Layout\Event\AboutRendering;

/**
 * Renders the ft_about_* points at their positions in the about region, in a
 * buffer of their own as the markup-hook runner renders a point.
 */
final class AboutObserver {
	public const POINTS = array(
		AboutRendering::START			=> 'ft_about_output_start',
		AboutRendering::PRE_QUICKJUMP	=> 'ft_about_pre_quickjump',
		AboutRendering::PRE_COPYRIGHT	=> 'ft_about_pre_copyright',
		AboutRendering::END				=> 'ft_about_end',
	);

	public function __construct(private readonly PointEvaluator $points) {}

	public function observe(AboutRendering $event): void {
		if (!LegacyScope::attached(self::POINTS[$event->position()]))
			return;

		$event->append($this->points->render(self::POINTS[$event->position()], LegacyScope::with(array()), $event));
	}
}
