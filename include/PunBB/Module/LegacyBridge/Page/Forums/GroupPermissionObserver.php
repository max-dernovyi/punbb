<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Forums;

use PunBB\Module\Forums\Event\GroupPermissionRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the points of a group's fieldset of the forum's permissions with the
 * group's row as $cur_perm, what the checkboxes show as $read_forum,
 * $post_replies and $post_topics, whether each is the default as
 * $read_forum_def, $post_replies_def and $post_topics_def, and the form's
 * counts in $forum_page, read back for the fields that follow.
 */
final class GroupPermissionObserver {
	public const POINTS = array(
		GroupPermissionRendering::PRE_CUR_GROUP_PERMISSIONS_FIELDSET		=> 'afo_edit_forum_pre_cur_group_permissions_fieldset',
		GroupPermissionRendering::PRE_CUR_GROUP_READ_FORUM_PERMISSION		=> 'afo_edit_forum_pre_cur_group_read_forum_permission',
		GroupPermissionRendering::PRE_CUR_GROUP_POST_REPLIES_PERMISSION		=> 'afo_edit_forum_pre_cur_group_post_replies_permission',
		GroupPermissionRendering::PRE_CUR_GROUP_POST_TOPICS_PERMISSION		=> 'afo_edit_forum_pre_cur_group_post_topics_permission',
		GroupPermissionRendering::POST_CUR_GROUP_POST_TOPICS_PERMISSION		=> 'afo_edit_forum_post_cur_group_post_topics_permission',
		GroupPermissionRendering::PRE_CUR_GROUP_PERMISSIONS_FIELDSET_END	=> 'afo_edit_forum_pre_cur_group_permissions_fieldset_end',
		GroupPermissionRendering::CUR_GROUP_PERMISSIONS_FIELDSET_END		=> 'afo_edit_forum_cur_group_permissions_fieldset_end',
	);

	public function __construct(private readonly PageScope $scope, private readonly ForumsRows $rows) {}

	public function observe(GroupPermissionRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['cur_perm'] = $this->rows->permissions($event->group());
		$shown = $event->shown();
		$GLOBALS['read_forum'] = $GLOBALS['read_forum_def'] = $shown->readForum();
		$GLOBALS['post_replies'] = $shown->postReplies();
		$GLOBALS['post_topics'] = $shown->postTopics();
		$GLOBALS['post_replies_def'] = $shown->postReplies() === $event->group()->postsReplies();
		$GLOBALS['post_topics_def'] = $shown->postTopics() === $event->group()->postsTopics();

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
