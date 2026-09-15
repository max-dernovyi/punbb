<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Ranks;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Ranks\Event\RanksRendering;

/**
 * Renders the ranks page's markup points outside the list of ranks at their
 * positions, with the forms' counts in $forum_page, read back for the fields
 * that follow.
 */
final class RanksRenderingObserver {
	public const POINTS = array(
		RanksRendering::MAIN_OUTPUT_START			=> 'ark_main_output_start',
		RanksRendering::PRE_ADD_RANK_FIELDSET		=> 'ark_pre_add_rank_fieldset',
		RanksRendering::PRE_ADD_RANK_TITLE			=> 'ark_pre_add_rank_title',
		RanksRendering::PRE_ADD_RANK_MIN_POSTS		=> 'ark_pre_add_rank_min_posts',
		RanksRendering::PRE_ADD_RANK_SUBMIT			=> 'ark_pre_add_rank_submit',
		RanksRendering::PRE_ADD_RANK_FIELDSET_END	=> 'ark_pre_add_rank_fieldset_end',
		RanksRendering::ADD_RANK_FIELDSET_END		=> 'ark_add_rank_fieldset_end',
		RanksRendering::END							=> 'ark_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(RanksRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
