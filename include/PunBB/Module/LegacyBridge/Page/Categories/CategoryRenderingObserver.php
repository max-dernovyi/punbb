<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Categories;

use PunBB\Module\Categories\Event\CategoryRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the points of a category's fieldset with the category as
 * $cur_category and the form's counts in $forum_page, read back for the
 * fields that follow.
 */
final class CategoryRenderingObserver {
	public const POINTS = array(
		CategoryRendering::PRE_EDIT_CUR_CAT_FIELDSET		=> 'acg_pre_edit_cur_cat_fieldset',
		CategoryRendering::PRE_EDIT_CAT_NAME				=> 'acg_pre_edit_cat_name',
		CategoryRendering::PRE_EDIT_CAT_POSITION			=> 'acg_pre_edit_cat_position',
		CategoryRendering::PRE_EDIT_CUR_CAT_FIELDSET_END	=> 'acg_pre_edit_cur_cat_fieldset_end',
		CategoryRendering::EDIT_CUR_CAT_FIELDSET_END		=> 'acg_edit_cur_cat_fieldset_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(CategoryRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$category = $event->category();
		$GLOBALS['cur_category'] = array('id' => $category->id(), 'cat_name' => $category->name(), 'disp_position' => $category->position());

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
