<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout\Observer;

use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Layout\Event\HeadAssembling;

/**
 * Runs hd_head with the head entries as $forum_head.
 */
final class HeadObserver {
	public function __construct(private readonly PointEvaluator $points) {}

	public function observe(HeadAssembling $event): void {
		if (!LegacyScope::attached('hd_head'))
			return;

		$forum_head = array();
		foreach ($event->names() as $name)
			$forum_head[$name] = (string) $event->entry($name);

		$this->points->run('hd_head', LegacyScope::with(array('forum_head' => &$forum_head)), $event);

		foreach ($event->names() as $name)
			$event->remove($name);

		foreach (Markers::entries($forum_head) as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
