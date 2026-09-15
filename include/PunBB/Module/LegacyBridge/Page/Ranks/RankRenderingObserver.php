<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Ranks;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Ranks\Event\RankRendering;

/**
 * Renders the points of a stored rank's fieldset with the rank as $cur_rank,
 * its place in the list as $rank_key and the form's counts in $forum_page,
 * read back for the fields that follow.
 */
final class RankRenderingObserver {
	public const POINTS = array(
		RankRendering::PRE_EDIT_CUR_RANK_FIELDSET		=> 'ark_pre_edit_cur_rank_fieldset',
		RankRendering::PRE_EDIT_CUR_RANK_TITLE			=> 'ark_pre_edit_cur_rank_title',
		RankRendering::PRE_EDIT_CUR_RANK_MIN_POSTS		=> 'ark_pre_edit_cur_rank_min_posts',
		RankRendering::PRE_EDIT_CUR_RANK_SUBMIT			=> 'ark_pre_edit_cur_rank_submit',
		RankRendering::PRE_EDIT_CUR_RANK_FIELDSET_END	=> 'ark_pre_edit_cur_rank_fieldset_end',
		RankRendering::EDIT_CUR_RANK_FIELDSET_END		=> 'ark_edit_cur_rank_fieldset_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(RankRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$rank = $event->rank();
		$GLOBALS['rank_key'] = $event->number() - 1;
		$GLOBALS['cur_rank'] = array('id' => $rank->id(), 'rank' => $rank->title(), 'min_posts' => $rank->minPosts());

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
