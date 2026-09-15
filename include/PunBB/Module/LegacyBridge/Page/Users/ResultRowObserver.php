<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Users\Event\ResultRowAssembling;
use PunBB\Module\Users\Event\SearchSelected;

/**
 * Runs the point at each stage of a row of results, with its number as
 * $forum_page['item_count'], its classes as ['item_style'] and its cells as
 * ['table_row'], the classes and cells read back; a row of addresses as
 * $cur_ip, a poster as $user and their account as $user_data, a user found as
 * $user_data, each with any column a query point added.
 */
final class ResultRowObserver {
	public const POINTS = array(
		SearchSelected::IP_STATS	=> array(
			ResultRowAssembling::START			=> 'aus_ip_stats_pre_row_generation',
			ResultRowAssembling::CELLS			=> 'aus_ip_stats_pre_row_output',
			ResultRowAssembling::EMPTY_START	=> 'aus_ip_stats_pre_no_results_row_generation',
			ResultRowAssembling::EMPTY_CELLS	=> 'aus_ip_stats_pre_no_results_row_output',
		),
		SearchSelected::SHOW_USERS	=> array(
			ResultRowAssembling::START			=> 'aus_show_users_pre_row_generation',
			ResultRowAssembling::CELLS			=> 'aus_show_users_pre_row_output',
			ResultRowAssembling::EMPTY_START	=> 'aus_show_users_pre_no_results_row_generation',
			ResultRowAssembling::EMPTY_CELLS	=> 'aus_show_users_pre_no_results_row_output',
		),
		SearchSelected::FIND_USER	=> array(
			ResultRowAssembling::START			=> 'aus_find_user_pre_row_generation',
			ResultRowAssembling::CELLS			=> 'aus_find_user_pre_row_output',
			ResultRowAssembling::EMPTY_START	=> 'aus_find_user_pre_no_results_row_generation',
			ResultRowAssembling::EMPTY_CELLS	=> 'aus_find_user_pre_no_results_row_output',
		),
	);

	public function __construct(private readonly PageScope $scope, private readonly UsersRows $rows) {}

	public function observe(ResultRowAssembling $event): void {
		$point = self::POINTS[$event->search()][$event->stage()];
		if (!LegacyScope::attached($point))
			return;

		$address = $event->address();
		if ($address !== null)
			$GLOBALS['cur_ip'] = $this->rows->address($address);

		$poster = $event->poster();
		if ($poster !== null)
			$GLOBALS['user'] = $this->rows->poster($poster);

		$user = $event->user();
		if ($user !== null || $poster !== null)
			$GLOBALS['user_data'] = $user !== null ? $this->rows->user($user) : false;

		$empty = in_array($event->stage(), array(ResultRowAssembling::EMPTY_START, ResultRowAssembling::EMPTY_CELLS), true);
		$cells = in_array($event->stage(), array(ResultRowAssembling::CELLS, ResultRowAssembling::EMPTY_CELLS), true);

		$page = ForumPage::all();
		if (!$empty)
		{
			$page['item_count'] = $event->number();
			$page['item_style'] = $event->style();
		}

		if ($cells)
		{
			$page['table_row'] = array();
			foreach ($event->names() as $name)
				$page['table_row'][$name] = (string) $event->entry($name);
		}
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		if (!$empty)
			$event->setStyle(Markers::markup(ForumPage::get('item_style')));

		if ($cells)
		{
			$returned = Markers::entries(ForumPage::get('table_row'));

			foreach ($event->names() as $name)
				if (!array_key_exists($name, $returned))
					$event->remove($name);

			foreach ($returned as $name => $markup)
				$event->set((string) $name, $markup);
		}
	}
}
