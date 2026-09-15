<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Extern;

use PunBB\Module\Extern\Event\StatisticsShowing;
use PunBB\Module\Extern\Model\Statistics;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs ex_pre_stats_output with the statistics as $stats, read back.
 */
final class StatisticsShowingObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(StatisticsShowing $event): void {
		if (!LegacyScope::attached('ex_pre_stats_output'))
			return;

		$statistics = $event->statistics();
		$GLOBALS['stats'] = array(
			'total_users'	=> $statistics->userCount(),
			'last_user'		=> array('id' => $statistics->newestUserId(), 'username' => $statistics->newestUsername()),
			'total_topics'	=> $statistics->topicCount(),
			'total_posts'	=> $statistics->postCount(),
		);

		$this->scope->observe('ex_pre_stats_output', $event);

		$stats = is_array($GLOBALS['stats'] ?? null) ? $GLOBALS['stats'] : array();
		$last = is_array($stats['last_user'] ?? null) ? $stats['last_user'] : array();

		$event->replace(new Statistics((int) Markers::markup($stats['total_users'] ?? 0), (int) Markers::markup($last['id'] ?? 0), Markers::markup($last['username'] ?? ''),
			(int) Markers::markup($stats['total_topics'] ?? 0), (int) Markers::markup($stats['total_posts'] ?? 0)));
	}
}
