<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Search;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Search\Event\ResultsRendering;

/**
 * Renders se_results_output_start and se_results_end. At the start the links
 * above and below the results and their summary are in $forum_page, and the
 * links are read back.
 */
final class ResultsRenderingObserver {
	public const POINTS = array(
		ResultsRendering::START	=> 'se_results_output_start',
		ResultsRendering::END	=> 'se_results_end',
	);

	/** $forum_page key => the group of parts it holds */
	private const OPTIONS = array(
		'main_head_options'	=> ResultsRendering::HEAD_OPTIONS,
		'main_foot_options'	=> ResultsRendering::FOOT_OPTIONS,
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ResultsRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		if ($event->position() === ResultsRendering::START)
		{
			foreach (self::OPTIONS as $key => $group)
				ForumPage::set($key, SearchParts::group($event, $group));

			ForumPage::set('items_info', $event->itemsInfo());
		}

		$event->append($this->scope->renderObserved($point, $event));

		if ($event->position() === ResultsRendering::START)
			foreach (self::OPTIONS as $key => $group)
				SearchParts::replace($event, $group, ForumPage::get($key));
	}
}
