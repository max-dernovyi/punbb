<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Categories;

use PunBB\Module\Categories\Event\CategoryDeletionRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders acg_del_cat_output_start with the form's action and hidden fields in
 * $forum_page, the fields read back, and acg_del_cat_end.
 */
final class CategoryDeletionObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(CategoryDeletionRendering $event): void {
		if ($event->position() === CategoryDeletionRendering::END)
		{
			$event->append($this->scope->renderObserved('acg_del_cat_end', $event));
			return;
		}

		if (!LegacyScope::attached('acg_del_cat_output_start'))
			return;

		$fields = array();
		foreach ($event->names() as $name)
			$fields[$name] = (string) $event->entry($name);

		ForumPage::set('form_action', $event->action());
		ForumPage::set('hidden_fields', $fields);

		$event->append($this->scope->renderObserved('acg_del_cat_output_start', $event));

		$fields = Markers::entries(ForumPage::get('hidden_fields'));
		foreach ($event->names() as $name)
			if (!isset($fields[$name]))
				$event->remove($name);

		foreach ($fields as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
