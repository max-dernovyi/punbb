<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Users\Event\ResultsEnding;
use PunBB\Module\Users\Event\SearchSelected;

/**
 * Renders the point ending a search's results.
 */
final class ResultsEndingObserver {
	public const POINTS = array(
		SearchSelected::IP_STATS	=> 'aus_ip_stats_end',
		SearchSelected::SHOW_USERS	=> 'aus_show_users_end',
		SearchSelected::FIND_USER	=> 'aus_find_user_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ResultsEnding $event): void {
		$event->append($this->scope->renderObserved(self::POINTS[$event->search()], $event));
	}
}
