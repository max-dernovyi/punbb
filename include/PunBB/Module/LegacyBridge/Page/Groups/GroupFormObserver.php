<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Groups;

use PunBB\Module\Groups\Event\GroupFormRendering;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;

/**
 * Renders the points of the form adding or editing a group at their positions,
 * with $mode and the form's counts in $forum_page, read back for the fields
 * that follow. The email interval's point runs at both its sites.
 */
final class GroupFormObserver {
	public const POINTS = array(
		GroupFormRendering::OUTPUT_START						=> 'agr_add_edit_group_output_start',
		GroupFormRendering::PRE_BASIC_DETAILS_FIELDSET			=> 'agr_add_edit_group_pre_basic_details_fieldset',
		GroupFormRendering::PRE_GROUP_TITLE						=> 'agr_add_edit_group_pre_group_title',
		GroupFormRendering::PRE_USER_TITLE						=> 'agr_add_edit_group_pre_user_title',
		GroupFormRendering::PRE_BASIC_DETAILS_FIELDSET_END		=> 'agr_add_edit_group_pre_basic_details_fieldset_end',
		GroupFormRendering::BASIC_DETAILS_FIELDSET_END			=> 'agr_add_edit_group_basic_details_fieldset_end',
		GroupFormRendering::PRE_PERMISSIONS_FIELDSET			=> 'agr_add_edit_group_pre_permissions_fieldset',
		GroupFormRendering::PRE_MOD_PERMISSIONS_FIELDSET		=> 'agr_add_edit_group_pre_mod_permissions_fieldset',
		GroupFormRendering::PRE_ALLOW_MODERATE_CHECKBOX			=> 'agr_add_edit_group_pre_allow_moderate_checkbox',
		GroupFormRendering::PRE_ALLOW_MOD_EDIT_PROFILES_CHECKBOX	=> 'agr_add_edit_group_pre_allow_mod_edit_profiles_checkbox',
		GroupFormRendering::PRE_ALLOW_MOD_EDIT_USERBANE_CHECKBOX	=> 'agr_add_edit_group_pre_allow_mod_edit_userbane_checkbox',
		GroupFormRendering::PRE_ALLOW_MOD_CHANGE_PASS_CHECKBOX	=> 'agr_add_edit_group_pre_allow_mod_change_pass_checkbox',
		GroupFormRendering::PRE_ALLOW_MOD_BAN_USERS_CHECKBOX	=> 'agr_add_edit_group_pre_allow_mod_ban_users_checkbox',
		GroupFormRendering::PRE_MOD_PERMISSIONS_FIELDSET_END	=> 'agr_add_edit_group_pre_mod_permissions_fieldset_end',
		GroupFormRendering::MOD_PERMISSIONS_FIELDSET_END		=> 'agr_add_edit_group_mod_permissions_fieldset_end',
		GroupFormRendering::PRE_ALLOW_READ_BOARD_CHECKBOX		=> 'agr_add_edit_group_pre_allow_read_board_checkbox',
		GroupFormRendering::PRE_ALLOW_VIEW_USERS_CHECKBOX		=> 'agr_add_edit_group_pre_allow_view_users_checkbox',
		GroupFormRendering::PRE_ALLOW_POST_REPLIES_CHECKBOX		=> 'agr_add_edit_group_pre_allow_post_replies_checkbox',
		GroupFormRendering::PRE_ALLOW_POST_TOPICS_CHECKBOX		=> 'agr_add_edit_group_pre_allow_post_topics_checkbox',
		GroupFormRendering::PRE_ALLOW_EDIT_POSTS_CHECKBOX		=> 'agr_add_edit_group_pre_allow_edit_posts_checkbox',
		GroupFormRendering::PRE_ALLOW_DELETE_POSTS_CHECKBOX		=> 'agr_add_edit_group_pre_allow_delete_posts_checkbox',
		GroupFormRendering::PRE_ALLOW_DELETE_TOPICS_CHECKBOX	=> 'agr_add_edit_group_pre_allow_delete_topics_checkbox',
		GroupFormRendering::PRE_ALLOW_SET_USER_TITLE_CHECKBOX	=> 'agr_add_edit_group_pre_allow_set_user_title_checkbox',
		GroupFormRendering::PRE_ALLOW_SEARCH_CHECKBOX			=> 'agr_add_edit_group_pre_allow_search_checkbox',
		GroupFormRendering::PRE_ALLOW_SEARCH_USERS_CHECKBOX		=> 'agr_add_edit_group_pre_allow_search_users_checkbox',
		GroupFormRendering::PRE_ALLOW_SEND_EMAIL_CHECKBOX		=> 'agr_add_edit_group_pre_allow_send_email_checkbox',
		GroupFormRendering::PRE_USER_PERMISSIONS_FIELDSET_END	=> 'agr_add_edit_group_pre_user_permissions_fieldset_end',
		GroupFormRendering::USER_PERMISSIONS_FIELDSET_END		=> 'agr_add_edit_group_user_permissions_fieldset_end',
		GroupFormRendering::PRE_FLOOD_FIELDSET					=> 'agr_add_edit_group_pre_flood_fieldset',
		GroupFormRendering::PRE_POST_INTERVAL					=> 'agr_add_edit_group_pre_post_interval',
		GroupFormRendering::PRE_SEARCH_INTERVAL					=> 'agr_add_edit_group_pre_search_interval',
		GroupFormRendering::PRE_EMAIL_INTERVAL					=> 'agr_add_edit_group_pre_email_interval',
		GroupFormRendering::PRE_EMAIL_INTERVAL_FIELD			=> 'agr_add_edit_group_pre_email_interval',
		GroupFormRendering::PRE_FLOOD_FIELDSET_END				=> 'agr_add_edit_group_pre_flood_fieldset_end',
		GroupFormRendering::FLOOD_FIELDSET_END					=> 'agr_add_edit_group_flood_fieldset_end',
		GroupFormRendering::END									=> 'agr_add_edit_group_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(GroupFormRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['mode'] = $event->isAdding() ? 'add' : 'edit';

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
