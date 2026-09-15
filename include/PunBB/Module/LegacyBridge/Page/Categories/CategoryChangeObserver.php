<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Categories;

use PunBB\Module\Categories\Event\CategoryChangeStep;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point at each step of changing the categories, with the change in
 * the variables admin/categories.php held it in: $new_cat_name and
 * $new_cat_pos, $cat_to_delete, and $cat_name and $cat_order keyed by id.
 */
final class CategoryChangeObserver {
	public const POINTS = array(
		CategoryChangeStep::ADDING		=> 'acg_add_cat_form_submitted',
		CategoryChangeStep::ADDED		=> 'acg_add_cat_pre_redirect',
		CategoryChangeStep::DELETING	=> 'acg_del_cat_form_submitted',
		CategoryChangeStep::DELETED		=> 'acg_del_cat_pre_redirect',
		CategoryChangeStep::UPDATING	=> 'acg_update_cats_form_submitted',
		CategoryChangeStep::UPDATED		=> 'acg_update_cats_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(CategoryChangeStep $event): void {
		$categories = $event->categories();

		switch ($event->step())
		{
			case CategoryChangeStep::ADDING:
			case CategoryChangeStep::ADDED:
				$GLOBALS['new_cat_name'] = isset($categories[0]) ? $categories[0]->name() : '';
				$GLOBALS['new_cat_pos'] = isset($categories[0]) ? $categories[0]->position() : 0;
				break;

			case CategoryChangeStep::DELETING:
			case CategoryChangeStep::DELETED:
				$GLOBALS['cat_to_delete'] = isset($categories[0]) ? $categories[0]->id() : 0;
				break;

			default:
				$names = $orders = array();
				foreach ($categories as $category)
				{
					$names[$category->id()] = $category->name();
					$orders[$category->id()] = $category->position();
				}

				$GLOBALS['cat_name'] = $names;
				$GLOBALS['cat_order'] = $orders;
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
