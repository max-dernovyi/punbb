<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\SearchCaching;

/**
 * Runs sf_fn_create_search_cache_start and sf_fn_create_search_cache_end
 * with what the search asks for in create_search_cache()'s parameters, and
 * once stored its id in $search_id; a value the code returns stops the search.
 */
final class SearchCachingObserver {
	public const POINTS = array(
		SearchCaching::START	=> 'sf_fn_create_search_cache_start',
		SearchCaching::STORED	=> 'sf_fn_create_search_cache_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(SearchCaching $event): void {
		$criteria = $event->criteria();
		$keywords = $criteria->keywords();
		$author = $criteria->author();
		$search_in = $criteria->searchIn();
		$forum = $criteria->forumIds();
		$show_as = $criteria->showAs();
		$sort_by = $criteria->sortBy();
		$sort_dir = $criteria->sortDir();
		$search_id = $event->searchId();

		$returned = $this->scope->observe(self::POINTS[$event->step()], $event, array(
			'keywords'	=> &$keywords,
			'author'	=> &$author,
			'search_in'	=> &$search_in,
			'forum'		=> &$forum,
			'show_as'	=> &$show_as,
			'sort_by'	=> &$sort_by,
			'sort_dir'	=> &$sort_dir,
			'search_id'	=> &$search_id,
		));

		if ($returned !== null)
			$event->stop();
	}
}
