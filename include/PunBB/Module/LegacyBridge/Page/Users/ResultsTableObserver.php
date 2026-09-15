<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Users\Event\ResultsTableAssembling;
use PunBB\Module\Users\Event\SearchSelected;

/**
 * Runs the point starting a search's results, with the header cells as
 * $forum_page['table_header'], the options as ['main_head_options'] and
 * ['main_foot_options'], and how many were found as ['num_users'], all read back.
 */
final class ResultsTableObserver {
	public const POINTS = array(
		SearchSelected::IP_STATS	=> 'aus_ip_stats_output_start',
		SearchSelected::SHOW_USERS	=> 'aus_show_users_output_start',
		SearchSelected::FIND_USER	=> 'aus_find_user_output_start',
	);

	/** $forum_page key => the group of parts it holds */
	private const GROUPS = array(
		'table_header'		=> ResultsTableAssembling::HEADER,
		'main_head_options'	=> ResultsTableAssembling::HEAD_OPTIONS,
		'main_foot_options'	=> ResultsTableAssembling::FOOT_OPTIONS,
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ResultsTableAssembling $event): void {
		$point = self::POINTS[$event->search()];
		if (!LegacyScope::attached($point))
			return;

		$page = ForumPage::all();
		foreach (self::GROUPS as $key => $group)
		{
			$page[$key] = array();
			foreach ($event->names($group) as $name)
				$page[$key][$name] = (string) $event->entry($group, $name);
		}
		$page['num_users'] = $event->count();
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		foreach (self::GROUPS as $key => $group)
		{
			$returned = Markers::entries(ForumPage::get($key));

			foreach ($event->names($group) as $name)
				if (!array_key_exists($name, $returned))
					$event->remove($group, $name);

			foreach ($returned as $name => $markup)
				$event->set($group, (string) $name, $markup);
		}
	}
}
