<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Post;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Post\Event\PostCheckboxesAssembling;

/**
 * Renders po_pre_optional_fieldset with the checkboxes in $forum_page['checkboxes']
 * and the form's counts, all read back.
 */
final class PostCheckboxesObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(PostCheckboxesAssembling $event): void {
		if (!LegacyScope::attached('po_pre_optional_fieldset'))
			return;

		$checkboxes = array();
		foreach ($event->names() as $name)
			$checkboxes[$name] = (string) $event->entry($name);

		ForumPage::set('checkboxes', $checkboxes);
		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved('po_pre_optional_fieldset', $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));

		foreach ($event->names() as $name)
			$event->remove($name);

		foreach (Markers::entries(ForumPage::get('checkboxes')) as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
