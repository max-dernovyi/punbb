<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Bans;

use PunBB\Module\Bans\Event\BansRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders aba_main_output_start with the form's action, hidden fields and
 * counts in $forum_page, the fields and counts read back, and aba_end.
 */
final class BansRenderingObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(BansRendering $event): void {
		if ($event->position() === BansRendering::END)
		{
			$event->append($this->scope->renderObserved('aba_end', $event));
			return;
		}

		if (!LegacyScope::attached('aba_main_output_start'))
			return;

		$fields = array();
		foreach ($event->names() as $name)
			$fields[$name] = (string) $event->entry($name);

		ForumPage::set('form_action', $event->action());
		ForumPage::set('hidden_fields', $fields);
		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved('aba_main_output_start', $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));

		$fields = Markers::entries(ForumPage::get('hidden_fields'));
		foreach ($event->names() as $name)
			if (!isset($fields[$name]))
				$event->remove($name);

		foreach ($fields as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
