<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Edit;

use PunBB\Module\Edit\Event\EditCheckboxesAssembling;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders ed_pre_checkbox_display with the checkboxes in $forum_page['checkboxes']
 * and the form's counts, all read back.
 */
final class EditCheckboxesObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(EditCheckboxesAssembling $event): void {
		if (!LegacyScope::attached('ed_pre_checkbox_display'))
			return;

		$checkboxes = array();
		foreach ($event->names() as $name)
			$checkboxes[$name] = (string) $event->entry($name);

		ForumPage::set('checkboxes', $checkboxes);
		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved('ed_pre_checkbox_display', $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));

		foreach ($event->names() as $name)
			$event->remove($name);

		foreach (Markers::entries(ForumPage::get('checkboxes')) as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
