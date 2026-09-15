<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\ResultsHeadAssembling;

/**
 * Runs sf_fn_generate_search_crumbs_start with the quick search in $action and
 * the links above and below the results in $forum_page, which are read back.
 */
final class ResultsHeadObserver {
	/** $forum_page key => the group of parts it holds */
	private const OPTIONS = array(
		'main_head_options'	=> ResultsHeadAssembling::HEAD_OPTIONS,
		'main_foot_options'	=> ResultsHeadAssembling::FOOT_OPTIONS,
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ResultsHeadAssembling $event): void {
		foreach (self::OPTIONS as $key => $group)
			ForumPage::set($key, SearchParts::group($event, $group));

		$action = $event->listing()->action();

		$this->scope->observe('sf_fn_generate_search_crumbs_start', $event, array('action' => &$action));

		foreach (self::OPTIONS as $key => $group)
			SearchParts::replace($event, $group, ForumPage::get($key));
	}
}
