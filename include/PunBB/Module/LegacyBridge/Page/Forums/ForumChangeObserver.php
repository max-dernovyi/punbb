<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Forums;

use PunBB\Module\Forums\Event\ForumChangeStep;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Runs the point at each step of changing the forums, with the change in the
 * variables admin/forums.php held it in: $add_to_cat, $forum_name and
 * $position; $forum_to_delete; $positions keyed by forum; $forum_id, and once
 * saved $forum_name, $forum_desc, $cat_id, $sort_by and $redirect_url.
 */
final class ForumChangeObserver {
	public const POINTS = array(
		ForumChangeStep::ADDING		=> 'afo_add_forum_form_submitted',
		ForumChangeStep::ADDED		=> 'afo_add_forum_pre_redirect',
		ForumChangeStep::DELETING	=> 'afo_del_forum_form_submitted',
		ForumChangeStep::DELETED	=> 'afo_del_forum_pre_redirect',
		ForumChangeStep::REORDERING	=> 'afo_update_positions_form_submitted',
		ForumChangeStep::REORDERED	=> 'afo_update_positions_pre_redirect',
		ForumChangeStep::SELECTED	=> 'afo_edit_forum_selected',
		ForumChangeStep::SAVING		=> 'afo_save_forum_form_submitted',
		ForumChangeStep::SAVED		=> 'afo_save_forum_pre_redirect',
		ForumChangeStep::REVERTING	=> 'afo_revert_perms_form_submitted',
		ForumChangeStep::REVERTED	=> 'afo_revert_perms_pre_redirect',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ForumChangeStep $event): void {
		$forum = $event->forum();

		switch ($event->step())
		{
			case ForumChangeStep::ADDING:
			case ForumChangeStep::ADDED:
				$GLOBALS['add_to_cat'] = $forum?->categoryId() ?? 0;
				$GLOBALS['forum_name'] = $forum?->name() ?? '';
				$GLOBALS['position'] = $forum?->position() ?? 0;
				break;

			case ForumChangeStep::DELETING:
			case ForumChangeStep::DELETED:
				$GLOBALS['forum_to_delete'] = $forum?->id() ?? 0;
				break;

			case ForumChangeStep::REORDERING:
				$positions = array();
				foreach ($event->positions() as $position)
					$positions[$position->forumId()] = $position->position();

				$GLOBALS['positions'] = $positions;
				break;

			case ForumChangeStep::SAVING:
				$GLOBALS['forum_name'] = $forum?->name() ?? '';
				$GLOBALS['forum_desc'] = $forum?->description() ?? '';
				$GLOBALS['cat_id'] = $forum?->categoryId() ?? 0;
				$GLOBALS['sort_by'] = $forum?->sortBy() ?? 0;
				$GLOBALS['redirect_url'] = $forum?->redirectUrl();
				// no break: the forum saved is the one selected
			case ForumChangeStep::SELECTED:
			case ForumChangeStep::REVERTING:
				$GLOBALS['forum_id'] = $forum?->id() ?? 0;
				break;
		}

		$this->scope->observe(self::POINTS[$event->step()], $event);
	}
}
