<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\SearchFormRendering;

/**
 * Renders the markup points of the search form at their positions, with the
 * form's counts, the links above it, the lines explaining it and the sort
 * options in $forum_page and the advanced form in $advanced_search, all read
 * back.
 */
final class SearchFormObserver {
	public const POINTS = array(
		SearchFormRendering::MAIN_OUTPUT_START					=> 'se_main_output_start',
		SearchFormRendering::PRE_CRITERIA_FIELDSET				=> 'se_pre_criteria_fieldset',
		SearchFormRendering::PRE_KEYWORDS						=> 'se_pre_keywords',
		SearchFormRendering::PRE_AUTHOR							=> 'se_pre_author',
		SearchFormRendering::PRE_SEARCH_IN						=> 'se_pre_search_in',
		SearchFormRendering::PRE_FORUM_FIELDSET					=> 'se_pre_forum_fieldset',
		SearchFormRendering::PRE_FORUM_CHECKLIST				=> 'se_pre_forum_checklist',
		SearchFormRendering::PRE_FORUM_FIELDSET_END				=> 'se_pre_forum_fieldset_end',
		SearchFormRendering::FORUM_FIELDSET_END					=> 'se_forum_fieldset_end',
		SearchFormRendering::CRITERIA_FIELDSET_END				=> 'se_criteria_fieldset_end',
		SearchFormRendering::PRE_RESULTS_FIELDSET				=> 'se_pre_results_fieldset',
		SearchFormRendering::PRE_SORT_BY						=> 'se_pre_sort_by',
		SearchFormRendering::PRE_SORT_ORDER_FIELDSET			=> 'se_pre_sort_order_fieldset',
		SearchFormRendering::PRE_SORT_ORDER						=> 'se_pre_sort_order',
		SearchFormRendering::PRE_SORT_ORDER_FIELDSET_END		=> 'se_pre_sort_order_fieldset_end',
		SearchFormRendering::PRE_DISPLAY_CHOICES_FIELDSET		=> 'se_pre_display_choices_fieldset',
		SearchFormRendering::PRE_DISPLAY_CHOICES				=> 'se_pre_display_choices',
		SearchFormRendering::NEW_DISPLAY_CHOICES				=> 'se_new_display_choices',
		SearchFormRendering::PRE_DISPLAY_CHOICES_FIELDSET_END	=> 'se_pre_display_choices_fieldset_end',
		SearchFormRendering::PRE_RESULTS_FIELDSET_END			=> 'se_pre_results_fieldset_end',
		SearchFormRendering::RESULTS_FIELDSET_END				=> 'se_results_fieldset_end',
		SearchFormRendering::END								=> 'se_end',
	);

	/** $forum_page key => the group of parts it holds */
	private const GROUPS = array(
		'main_head_options'	=> SearchFormRendering::HEAD_OPTIONS,
		'frm-info'			=> SearchFormRendering::INFO,
		'frm-sort'			=> SearchFormRendering::SORT,
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(SearchFormRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		foreach (self::GROUPS as $key => $group)
			ForumPage::set($key, SearchParts::group($event, $group));
		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());
		$GLOBALS['advanced_search'] = $event->advanced();

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
		foreach (self::GROUPS as $key => $group)
			SearchParts::replace($event, $group, ForumPage::get($key));
	}
}
