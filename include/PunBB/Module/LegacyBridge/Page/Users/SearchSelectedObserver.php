<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Users\Event\SearchSelected;

/**
 * Runs the point where a search of the users is selected, with its subject in
 * the variables admin/users.php held it in: $ip_stats; $ip; $form, $order_by,
 * $direction, and $conditions and $query_str not yet filled.
 */
final class SearchSelectedObserver {
	public const POINTS = array(
		SearchSelected::IP_STATS	=> 'aus_ip_stats_selected',
		SearchSelected::SHOW_USERS	=> 'aus_show_users_selected',
		SearchSelected::FIND_USER	=> 'aus_find_user_selected',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(SearchSelected $event): void {
		switch ($event->search())
		{
			case SearchSelected::IP_STATS:
				$GLOBALS['ip_stats'] = $event->userId();
				break;

			case SearchSelected::SHOW_USERS:
				$GLOBALS['ip'] = $event->address();
				break;

			case SearchSelected::FIND_USER:
				$form = array();
				foreach ($event->fieldNames() as $name)
					$form[$name] = $event->field($name);

				$GLOBALS['form'] = $form;
				$GLOBALS['order_by'] = $event->orderBy();
				$GLOBALS['direction'] = $event->descending() ? 'DESC' : 'ASC';
				$GLOBALS['conditions'] = $GLOBALS['query_str'] = array();
				break;
		}

		$this->scope->observe(self::POINTS[$event->search()], $event);
	}
}
