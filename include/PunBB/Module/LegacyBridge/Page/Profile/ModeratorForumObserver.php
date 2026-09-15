<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\KeptRows;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\ModeratorForumRendering;
use PunBB\Module\Profile\Model\Profiles;

/**
 * Renders pf_change_details_admin_forum_loop_start and _end around a forum of
 * the moderators' list, with the forum in $cur_forum, with any column a query
 * point added, and the form's counts in $forum_page, read back.
 */
final class ModeratorForumObserver {
	public const POINTS = array(
		ModeratorForumRendering::START	=> 'pf_change_details_admin_forum_loop_start',
		ModeratorForumRendering::END	=> 'pf_change_details_admin_forum_loop_end',
	);

	public function __construct(private readonly PageScope $scope, private readonly KeptRows $rows) {}

	public function observe(ModeratorForumRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$forum = $event->forum();

		ProfileState::publish($event->user());
		$GLOBALS['cur_forum'] = $this->rows->row($forum) ?? array(
			'cid'			=> $forum->categoryId(),
			'cat_name'		=> $forum->categoryName(),
			'fid'			=> $forum->forumId(),
			'forum_name'	=> $forum->forumName(),
			'moderators'	=> Profiles::serialized($forum->moderators()),
		);

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
