<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Users;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Users\Event\UserSearchFormRendering;

/**
 * Renders the points of the users' search forms at their positions, with the
 * forms' counts in $forum_page, read back for the fields that follow.
 */
final class UserSearchFormObserver {
	public const POINTS = array(
		UserSearchFormRendering::OUTPUT_START						=> 'aus_search_form_output_start',
		UserSearchFormRendering::PRE_USER_DETAILS_FIELDSET			=> 'aus_search_form_pre_user_details_fieldset',
		UserSearchFormRendering::PRE_USERNAME						=> 'aus_search_form_pre_username',
		UserSearchFormRendering::PRE_USER_TITLE						=> 'aus_search_form_pre_user_title',
		UserSearchFormRendering::PRE_REALNAME						=> 'aus_search_form_pre_realname',
		UserSearchFormRendering::PRE_LOCATION						=> 'aus_search_form_pre_location',
		UserSearchFormRendering::PRE_SIGNATURE						=> 'aus_search_form_pre_signature',
		UserSearchFormRendering::PRE_ADMIN_NOTE						=> 'aus_search_form_pre_admin_note',
		UserSearchFormRendering::PRE_USER_DETAILS_FIELDSET_END		=> 'aus_search_form_pre_user_details_fieldset_end',
		UserSearchFormRendering::USER_DETAILS_FIELDSET_END			=> 'aus_search_form_user_details_fieldset_end',
		UserSearchFormRendering::PRE_USER_CONTACTS_FIELDSET			=> 'aus_search_form_pre_user_contacts_fieldset',
		UserSearchFormRendering::PRE_EMAIL							=> 'aus_search_form_pre_email',
		UserSearchFormRendering::PRE_WEBSITE						=> 'aus_search_form_pre_website',
		UserSearchFormRendering::PRE_JABBER							=> 'aus_search_form_pre_jabber',
		UserSearchFormRendering::PRE_ICQ							=> 'aus_search_form_pre_icq',
		UserSearchFormRendering::PRE_MSN							=> 'aus_search_form_pre_msn',
		UserSearchFormRendering::PRE_AIM							=> 'aus_search_form_pre_aim',
		UserSearchFormRendering::PRE_YAHOO							=> 'aus_search_form_pre_yahoo',
		UserSearchFormRendering::PRE_USER_CONTACTS_FIELDSET_END		=> 'aus_search_form_pre_user_contacts_fieldset_end',
		UserSearchFormRendering::USER_CONTACTS_FIELDSET_END			=> 'aus_search_form_user_contacts_fieldset_end',
		UserSearchFormRendering::PRE_USER_ACTIVITY_FIELDSET			=> 'aus_search_form_pre_user_activity_fieldset',
		UserSearchFormRendering::PRE_MIN_POSTS						=> 'aus_search_form_pre_min_posts',
		UserSearchFormRendering::PRE_MAX_POSTS						=> 'aus_search_form_pre_max_posts',
		UserSearchFormRendering::PRE_LAST_POST_AFTER				=> 'aus_search_form_pre_last_post_after',
		UserSearchFormRendering::PRE_LAST_POST_BEFORE				=> 'aus_search_form_pre_last_post_before',
		UserSearchFormRendering::PRE_REGISTERED_AFTER				=> 'aus_search_form_pre_registered_after',
		UserSearchFormRendering::PRE_REGISTERED_BEFORE				=> 'aus_search_form_pre_registered_before',
		UserSearchFormRendering::PRE_USER_ACTIVITY_FIELDSET_END		=> 'aus_search_form_pre_user_activity_fieldset_end',
		UserSearchFormRendering::USER_ACTIVITY_FIELDSET_END			=> 'aus_search_form_user_activity_fieldset_end',
		UserSearchFormRendering::PRE_RESULTS_FIELDSET				=> 'aus_search_form_pre_results_fieldset',
		UserSearchFormRendering::PRE_SORT_BY						=> 'aus_search_form_pre_sort_by',
		UserSearchFormRendering::NEW_SORT_BY_OPTION					=> 'aus_search_form_new_sort_by_option',
		UserSearchFormRendering::PRE_SORT_ORDER						=> 'aus_search_form_pre_sort_order',
		UserSearchFormRendering::PRE_FILTER_GROUP					=> 'aus_search_form_pre_filter_group',
		UserSearchFormRendering::NEW_FILTER_GROUP_OPTION			=> 'aus_search_form_new_filter_group_option',
		UserSearchFormRendering::PRE_RESULTS_FIELDSET_END			=> 'aus_search_form_pre_results_fieldset_end',
		UserSearchFormRendering::RESULTS_FIELDSET_END				=> 'aus_search_form_results_fieldset_end',
		UserSearchFormRendering::PRE_IP_SEARCH_FIELDSET				=> 'aus_search_form_pre_ip_search_fieldset',
		UserSearchFormRendering::PRE_IP_ADDRESS						=> 'aus_search_form_pre_ip_address',
		UserSearchFormRendering::PRE_IP_SEARCH_FIELDSET_END			=> 'aus_search_form_pre_ip_search_fieldset_end',
		UserSearchFormRendering::IP_SEARCH_FIELDSET_END				=> 'aus_search_form_ip_search_fieldset_end',
		UserSearchFormRendering::END								=> 'aus_end',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(UserSearchFormRendering $event): void {
		$point = self::POINTS[$event->position()];
		if (!LegacyScope::attached($point))
			return;

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
