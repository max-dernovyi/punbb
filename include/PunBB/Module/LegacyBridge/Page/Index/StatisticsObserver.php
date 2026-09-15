<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Index;

use PunBB\Module\Index\Event\StatisticsAssembling;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs in_stats_pre_info_output with the lines as $stats_list, read back.
 */
final class StatisticsObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(StatisticsAssembling $event): void {
		if (!LegacyScope::attached('in_stats_pre_info_output'))
			return;

		$stats_list = array();
		foreach ($event->names() as $name)
			$stats_list[$name] = (string) $event->entry($name);

		$event->append($this->scope->renderObserved('in_stats_pre_info_output', $event, array('stats_list' => &$stats_list)));

		$lines = Markers::entries($stats_list);

		foreach ($event->names() as $name)
			if (!isset($lines[$name]))
				$event->remove($name);

		foreach ($lines as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
