<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Ranks;

use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Ranks\Event\RankChangeStep;

/**
 * Runs the point at each step of changing a rank, with the rank in $id, $rank
 * and $min_posts, as admin/ranks.php held it.
 */
final class RankChangeObserver {
	public const POINTS = array(
		RankChangeStep::ADDING		=> 'ark_add_rank_form_submitted',
		RankChangeStep::ADDED		=> 'ark_add_rank_pre_redirect',
		RankChangeStep::UPDATING	=> 'ark_update_form_submitted',
		RankChangeStep::UPDATED		=> 'ark_update_pre_redirect',
		RankChangeStep::REMOVING	=> 'ark_remove_form_submitted',
		RankChangeStep::REMOVED		=> 'ark_remove_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(RankChangeStep $event): void {
		$rank = $event->rank();

		if (!in_array($event->step(), array(RankChangeStep::ADDING, RankChangeStep::ADDED), true))
			$GLOBALS['id'] = $rank->id();

		if (!in_array($event->step(), array(RankChangeStep::REMOVING, RankChangeStep::REMOVED), true))
		{
			$GLOBALS['rank'] = $rank->title();
			$GLOBALS['min_posts'] = $rank->minPosts();
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
