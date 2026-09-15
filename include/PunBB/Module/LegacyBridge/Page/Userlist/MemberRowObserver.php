<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Userlist;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Userlist\Event\MemberRowAssembling;

/**
 * Runs ul_results_row_pre_data_output with the row's cells as $forum_page['table_row'] and its number as ['item_count'].
 */
final class MemberRowObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(MemberRowAssembling $event): void {
		if (!LegacyScope::attached('ul_results_row_pre_data_output'))
			return;

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['table_row'] = array();
		foreach ($event->names() as $name)
			$page['table_row'][$name] = (string) $event->entry($name);
		$page['item_count'] = $event->number();
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved('ul_results_row_pre_data_output', $event));

		RowCells::readBack($event, is_array($GLOBALS['forum_page'] ?? null) ? ($GLOBALS['forum_page']['table_row'] ?? null) : null);
	}
}
