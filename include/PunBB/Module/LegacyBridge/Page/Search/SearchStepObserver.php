<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Api\Data\ResultForumInterface;
use PunBB\Module\Search\Api\Data\ResultPostInterface;
use PunBB\Module\Search\Api\Data\ResultTopicInterface;
use PunBB\Module\Search\Event\SearchStep;
use PunBB\Module\Search\Api\Data\ListingInterface;

/**
 * Runs the point at each step of answering a search. What is listed is left
 * in $action, $show_as, $search_id and $url_type as search.php kept them;
 * once the results are read, their count in $num_hits, the page's results in
 * $search_set and the paging in $forum_page.
 */
final class SearchStepObserver {
	public const POINTS = array(
		SearchStep::QUERYING	=> 'se_pre_search_query',
		SearchStep::FETCHING	=> 'sf_fn_get_search_results_start',
		SearchStep::PAGINATED	=> 'sf_fn_get_search_results_end',
		SearchStep::FETCHED		=> 'se_post_results_fetched',
	);

	public function __construct(private readonly PageScope $scope, private readonly SearchRows $rows) {}

	public function observe(SearchStep $event): void {
		$listing = $event->listing();
		if ($listing !== null)
			self::publish($listing);

		if ($event->paged())
		{
			$GLOBALS['num_hits'] = $event->hits();
			$GLOBALS['search_set'] = array_map(fn (ResultPostInterface|ResultTopicInterface|ResultForumInterface $result): array => $this->rows->row($result), array_merge($event->posts(), $event->topics(), $event->forums()));

			$page = ForumPage::all();
			$page['per_page'] = $event->perPage();
			if ($event->hits() > 0)
			{
				$page['num_pages'] = $event->pageCount();
				$page['page'] = $event->page();
				$page['start_from'] = $event->offset();
				$page['finish_at'] = $event->last();
			}
			$GLOBALS['forum_page'] = $page;
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}

	private static function publish(ListingInterface $listing): void {
		$urls = $GLOBALS['forum_url'] ?? null;

		if ($listing->action() !== null)
			$GLOBALS['action'] = $listing->action();

		$GLOBALS['show_as'] = $listing->showAs();
		$GLOBALS['search_id'] = $listing->argument();
		$GLOBALS['url_type'] = is_array($urls) ? ($urls[$listing->url()] ?? null) : null;
	}
}
