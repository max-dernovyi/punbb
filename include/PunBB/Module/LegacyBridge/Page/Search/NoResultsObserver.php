<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\NoResultsShowing;

/**
 * Runs sf_fn_no_search_results_start with the quick search in $action and the
 * link to a new search in $forum_page['search_again'], which is read back.
 */
final class NoResultsObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(NoResultsShowing $event): void {
		ForumPage::set('search_again', $event->searchAgain());
		$action = $event->action() ?? 'search';

		$this->scope->observe('sf_fn_no_search_results_start', $event, array('action' => &$action));

		$event->setSearchAgain(Markers::markup(ForumPage::get('search_again')));
	}
}
