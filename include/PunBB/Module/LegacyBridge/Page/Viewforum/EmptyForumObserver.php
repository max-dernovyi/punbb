<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Viewforum;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Viewforum\Event\EmptyForumAssembling;

/**
 * Renders vf_no_results_row_pre_display with the row's lines in $forum_page['item_body']['subject'], read back.
 */
final class EmptyForumObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(EmptyForumAssembling $event): void {
		if (!LegacyScope::attached('vf_no_results_row_pre_display'))
			return;

		$lines = array();
		foreach ($event->names() as $name)
			$lines[$name] = (string) $event->entry($name);

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['item_body'] = array('subject' => $lines);
		$GLOBALS['forum_page'] = $page;

		$event->append($this->scope->renderObserved('vf_no_results_row_pre_display', $event));

		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$body = is_array($page['item_body'] ?? null) ? $page['item_body'] : array();

		foreach ($event->names() as $name)
			$event->remove($name);

		foreach (Markers::entries($body['subject'] ?? null) as $name => $markup)
			$event->set((string) $name, $markup);
	}
}
