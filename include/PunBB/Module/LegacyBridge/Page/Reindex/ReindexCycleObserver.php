<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Reindex;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Reindex\Event\ReindexCycleStep;

/**
 * Renders the point at each step of a rebuild cycle, with the cycle's figures
 * in the variables admin/reindex.php held them in: what it prints lands where
 * the page script's output stood when it ran.
 */
final class ReindexCycleObserver {
	public const POINTS = array(
		ReindexCycleStep::START	=> 'ari_cycle_start',
		ReindexCycleStep::END	=> 'ari_cycle_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ReindexCycleStep $event): void {
		$GLOBALS['per_page'] = $event->perCycle();
		$GLOBALS['start_at'] = $event->startAt();

		if ($event->step() === ReindexCycleStep::END)
		{
			$GLOBALS['post_id'] = $event->lastPostId();
			$GLOBALS['next_posts_to_proced'] = $event->nextPostId();
		}

		$event->append($this->scope->renderObserved(self::POINTS[$event->step()], $event));
	}
}
