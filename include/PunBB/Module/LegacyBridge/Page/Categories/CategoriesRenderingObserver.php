<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Categories;

use PunBB\Module\Categories\Event\CategoriesRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the categories page's markup points outside a category's fieldset at
 * their positions, with the forms' counts in $forum_page, read back for the
 * fields that follow. At the start the forms' action and hidden fields are in
 * $forum_page too, and the fields are read back.
 */
final class CategoriesRenderingObserver {
	public const POINTS = array(
		CategoriesRendering::MAIN_OUTPUT_START			=> 'acg_main_output_start',
		CategoriesRendering::PRE_ADD_CAT_FIELDSET		=> 'acg_pre_add_cat_fieldset',
		CategoriesRendering::PRE_NEW_CATEGORY_NAME		=> 'acg_pre_new_category_name',
		CategoriesRendering::PRE_NEW_CATEGORY_POSITION	=> 'acg_pre_new_category_position',
		CategoriesRendering::PRE_ADD_CAT_FIELDSET_END	=> 'acg_pre_add_cat_fieldset_end',
		CategoriesRendering::ADD_CAT_FIELDSET_END		=> 'acg_add_cat_fieldset_end',
		CategoriesRendering::POST_ADD_CAT_FORM			=> 'acg_post_add_cat_form',
		CategoriesRendering::PRE_DEL_CAT_FIELDSET		=> 'acg_pre_del_cat_fieldset',
		CategoriesRendering::PRE_DEL_CATEGORY_SELECT	=> 'acg_pre_del_category_select',
		CategoriesRendering::PRE_DEL_CAT_FIELDSET_END	=> 'acg_pre_del_cat_fieldset_end',
		CategoriesRendering::DEL_CAT_FIELDSET_END		=> 'acg_del_cat_fieldset_end',
		CategoriesRendering::POST_DEL_CAT_FORM			=> 'acg_post_del_cat_form',
		CategoriesRendering::EDIT_CAT_FIELDSETS_START	=> 'acg_edit_cat_fieldsets_start',
		CategoriesRendering::EDIT_CAT_FIELDSETS_END		=> 'acg_edit_cat_fieldsets_end',
		CategoriesRendering::POST_EDIT_CAT_FORM			=> 'acg_post_edit_cat_form',
		CategoriesRendering::END						=> 'acg_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(CategoriesRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$start = $event->position() === CategoriesRendering::MAIN_OUTPUT_START;
		if ($start)
		{
			$fields = array();
			foreach ($event->names() as $name)
				$fields[$name] = (string) $event->entry($name);

			ForumPage::set('form_action', $event->action());
			ForumPage::set('hidden_fields', $fields);
		}

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));

		if ($start)
		{
			$fields = Markers::entries(ForumPage::get('hidden_fields'));
			foreach ($event->names() as $name)
				if (!isset($fields[$name]))
					$event->remove($name);

			foreach ($fields as $name => $markup)
				$event->set((string) $name, $markup);
		}
	}
}
