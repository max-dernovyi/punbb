<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Userlist;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Userlist\Event\UserListRendering;

/**
 * Renders the member list's markup points at their positions, with the search
 * form's counts in $forum_page, read back for the fields that follow.
 */
final class UserListRenderingObserver {
	public const POINTS = array(
		UserListRendering::MAIN_OUTPUT_START			=> 'ul_main_output_start',
		UserListRendering::SEARCH_FIELDSET_START		=> 'ul_search_fieldset_start',
		UserListRendering::PRE_USERNAME					=> 'ul_pre_username',
		UserListRendering::PRE_GROUP_SELECT				=> 'ul_pre_group_select',
		UserListRendering::SEARCH_NEW_GROUP_OPTION		=> 'ul_search_new_group_option',
		UserListRendering::PRE_SORT_BY					=> 'ul_pre_sort_by',
		UserListRendering::NEW_SORT_BY_OPTION			=> 'ul_new_sort_by_option',
		UserListRendering::PRE_SORT_ORDER_FIELDSET		=> 'ul_pre_sort_order_fieldset',
		UserListRendering::PRE_SORT_ORDER				=> 'ul_pre_sort_order',
		UserListRendering::PRE_SORT_ORDER_FIELDSET_END	=> 'ul_pre_sort_order_fieldset_end',
		UserListRendering::PRE_SEARCH_FIELDSET_END		=> 'ul_pre_search_fieldset_end',
		UserListRendering::SEARCH_FIELDSET_END			=> 'ul_search_fieldset_end',
		UserListRendering::RESULTS_PRE_HEADER			=> 'ul_results_pre_header',
		UserListRendering::END							=> 'ul_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(UserListRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['group_count'] = $event->groupCount();
		$page['item_count'] = $event->itemCount();
		$page['fld_count'] = $event->fieldCount();
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved($point, $event));

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$event->count((int) Markers::markup($page['group_count'] ?? 0), (int) Markers::markup($page['item_count'] ?? 0), (int) Markers::markup($page['fld_count'] ?? 0));
	}
}
