<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Userlist;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Userlist\Event\MemberTableAssembling;

/**
 * Runs ul_results_pre_header_output with the header cells as $forum_page['table_header'].
 */
final class MemberTableObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(MemberTableAssembling $event): void {
		if (!LegacyScope::attached('ul_results_pre_header_output'))
			return;

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['table_header'] = array();
		foreach ($event->names() as $name)
			$page['table_header'][$name] = (string) $event->entry($name);
		$page['item_count'] = 0;
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved('ul_results_pre_header_output', $event));

		RowCells::readBack($event, is_array($GLOBALS['forum_page'] ?? null) ? ($GLOBALS['forum_page']['table_header'] ?? null) : null);
	}
}
