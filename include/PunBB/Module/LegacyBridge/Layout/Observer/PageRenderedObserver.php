<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout\Observer;

use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Layout\Event\PageRendered;

/**
 * Runs ft_end with the page as $tpl_main.
 */
final class PageRenderedObserver {
	public function __construct(private readonly PointEvaluator $points) {}

	public function observe(PageRendered $event): void {
		if (!LegacyScope::attached('ft_end'))
			return;

		$tpl_main = $event->html();

		$this->points->run('ft_end', LegacyScope::with(array('tpl_main' => &$tpl_main)), $event);

		$event->replace(Markers::markup($tpl_main));
	}
}
