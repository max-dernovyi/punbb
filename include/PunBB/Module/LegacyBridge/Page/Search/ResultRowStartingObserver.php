<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\ResultRowStarting;

/**
 * Renders se_results_loop_start with the result as $cur_set and the count in
 * $forum_page['item_count'], which is read back.
 */
final class ResultRowStartingObserver {
	public function __construct(private readonly PageScope $scope, private readonly SearchRows $rows) {}

	public function observe(ResultRowStarting $event): void {
		if (!LegacyScope::attached('se_results_loop_start'))
			return;

		$GLOBALS['cur_set'] = $this->rows->row($event->result());
		ForumPage::set('item_count', $event->itemCount());

		$event->append($this->scope->renderObserved('se_results_loop_start', $event));

		$event->count((int) Markers::markup(ForumPage::get('item_count') ?? $event->itemCount()));
	}
}
