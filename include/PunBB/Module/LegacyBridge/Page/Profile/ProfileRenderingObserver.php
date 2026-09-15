<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Profile;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Profile\Event\ProfileRendering;

/**
 * Renders the markup points of a profile's pages at their positions, with the
 * member in $user and the page's counts in $forum_page, read back for what
 * follows. The named markup a position carries is in $forum_page under the
 * key profile.php kept it in, and read back.
 */
final class ProfileRenderingObserver {
	/** @var array<string, array<string, string>> page => position => the point there */
	public const POINTS = array(
		ProfileRendering::CHANGE_PASS_KEY	=> array(
			'output_start'	=> 'pf_change_pass_key_output_start',
			'pre_errors'	=> 'pf_change_pass_key_pre_errors',
			'pre_fieldset'	=> 'pf_change_pass_key_pre_fieldset',
			'pre_new_password'	=> 'pf_change_pass_key_pre_new_password',
			'pre_new_password_confirm'	=> 'pf_change_pass_key_pre_new_password_confirm',
			'pre_fieldset_end'	=> 'pf_change_pass_key_pre_fieldset_end',
			'fieldset_end'	=> 'pf_change_pass_key_fieldset_end',
			'end'	=> 'pf_change_pass_key_end',
		),
		ProfileRendering::CHANGE_PASS	=> array(
			'output_start'	=> 'pf_change_pass_normal_output_start',
			'pre_errors'	=> 'pf_change_pass_normal_pre_errors',
			'pre_fieldset'	=> 'pf_change_pass_normal_pre_fieldset',
			'pre_old_password'	=> 'pf_change_pass_normal_pre_old_password',
			'pre_new_password'	=> 'pf_change_pass_normal_pre_new_password',
			'pre_new_password_confirm'	=> 'pf_change_pass_normal_pre_new_password_confirm',
			'pre_fieldset_end'	=> 'pf_change_pass_normal_pre_fieldset_end',
			'fieldset_end'	=> 'pf_change_pass_normal_fieldset_end',
			'end'	=> 'pf_change_pass_normal_end',
		),
		ProfileRendering::CHANGE_EMAIL	=> array(
			'output_start'	=> 'pf_change_email_normal_output_start',
			'pre_errors'	=> 'pf_change_email_pre_errors',
			'pre_fieldset'	=> 'pf_change_email_normal_pre_fieldset',
			'pre_new_email'	=> 'pf_change_email_normal_pre_new_email',
			'pre_password'	=> 'pf_change_email_normal_pre_password',
			'pre_fieldset_end'	=> 'pf_change_email_normal_pre_fieldset_end',
			'fieldset_end'	=> 'pf_change_email_normal_fieldset_end',
			'end'	=> 'pf_change_email_normal_end',
		),
		ProfileRendering::DELETE_USER	=> array(
			'output_start'	=> 'pf_delete_user_output_start',
			'pre_fieldset'	=> 'pf_delete_user_pre_fieldset',
			'pre_confirm_checkbox'	=> 'pf_delete_user_pre_confirm_checkbox',
			'pre_fieldset_end'	=> 'pf_delete_user_pre_fieldset_end',
			'fieldset_end'	=> 'pf_delete_user_fieldset_end',
			'end'	=> 'pf_delete_user_end',
		),
		ProfileRendering::DETAILS	=> array(
			'output_start'	=> 'pf_view_details_output_start',
			'pre_user_info'	=> 'pf_view_details_pre_user_info',
			'pre_user_ident_info'	=> 'pf_view_details_pre_user_ident_info',
			'pre_user_contact_info'	=> 'pf_view_details_pre_user_contact_info',
			'pre_user_activity_info'	=> 'pf_view_details_pre_user_activity_info',
			'pre_user_sig_info'	=> 'pf_view_details_pre_user_sig_info',
			'user_info_end'	=> 'pf_view_details_user_info_end',
			'end'	=> 'pf_view_details_end',
		),
		ProfileRendering::ABOUT	=> array(
			'output_start'	=> 'pf_change_details_about_output_start',
			'pre_user_info'	=> 'pf_change_details_about_pre_user_info',
			'pre_user_ident_info'	=> 'pf_change_details_about_pre_user_ident_info',
			'pre_user_contact_info'	=> 'pf_change_details_about_pre_user_contact_info',
			'pre_user_activity_info'	=> 'pf_change_details_about_pre_user_activity_info',
			'pre_user_sig_info'	=> 'pf_change_details_about_pre_user_sig_info',
			'pre_user_private_info'	=> 'pf_change_details_about_pre_user_private_info',
			'user_info_end'	=> 'pf_change_details_about_user_info_end',
			'end'	=> 'pf_change_details_about_end',
		),
		ProfileRendering::IDENTITY	=> array(
			'output_start'	=> 'pf_change_details_identity_output_start',
			'pre_errors'	=> 'pf_change_details_identity_pre_errors',
			'pre_req_info_fieldset'	=> 'pf_change_details_identity_pre_req_info_fieldset',
			'pre_username'	=> 'pf_change_details_identity_pre_username',
			'pre_email'	=> 'pf_change_details_identity_pre_email',
			'pre_req_info_fieldset_end'	=> 'pf_change_details_identity_pre_req_info_fieldset_end',
			'req_info_fieldset_end'	=> 'pf_change_details_identity_req_info_fieldset_end',
			'pre_personal_fieldset'	=> 'pf_change_details_identity_pre_personal_fieldset',
			'pre_realname'	=> 'pf_change_details_identity_pre_realname',
			'pre_title'	=> 'pf_change_details_identity_pre_title',
			'pre_location'	=> 'pf_change_details_identity_pre_location',
			'pre_admin_note'	=> 'pf_change_details_identity_pre_admin_note',
			'pre_num_posts'	=> 'pf_change_details_identity_pre_num_posts',
			'pre_personal_fieldset_end'	=> 'pf_change_details_identity_pre_personal_fieldset_end',
			'personal_fieldset_end'	=> 'pf_change_details_identity_personal_fieldset_end',
			'pre_url'	=> 'pf_change_details_identity_pre_url',
			'pre_facebook'	=> 'pf_change_details_identity_pre_facebook',
			'pre_twitter'	=> 'pf_change_details_identity_pre_twitter',
			'pre_linkedin'	=> 'pf_change_details_identity_pre_linkedin',
			'pre_jabber'	=> 'pf_change_details_identity_pre_jabber',
			'pre_skype'	=> 'pf_change_details_identity_pre_skype',
			'pre_msn'	=> 'pf_change_details_identity_pre_msn',
			'pre_icq'	=> 'pf_change_details_identity_pre_icq',
			'pre_aim'	=> 'pf_change_details_identity_pre_aim',
			'pre_yahoo'	=> 'pf_change_details_identity_pre_yahoo',
			'pre_contact_fieldset_end'	=> 'pf_change_details_identity_pre_contact_fieldset_end',
			'contact_fieldset_end'	=> 'pf_change_details_identity_contact_fieldset_end',
			'end'	=> 'pf_change_details_identity_end',
		),
		ProfileRendering::SETTINGS	=> array(
			'output_start'	=> 'pf_change_details_settings_output_start',
			'pre_local_fieldset'	=> 'pf_change_details_settings_pre_local_fieldset',
			'pre_language'	=> 'pf_change_details_settings_pre_language',
			'pre_timezone'	=> 'pf_change_details_settings_pre_timezone',
			'pre_dst_checkbox'	=> 'pf_change_details_settings_pre_dst_checkbox',
			'pre_time_format'	=> 'pf_change_details_settings_pre_time_format',
			'pre_date_format'	=> 'pf_change_details_settings_pre_date_format',
			'pre_local_fieldset_end'	=> 'pf_change_details_settings_pre_local_fieldset_end',
			'local_fieldset_end'	=> 'pf_change_details_settings_local_fieldset_end',
			'pre_display_fieldset'	=> 'pf_change_details_settings_pre_display_fieldset',
			'pre_style'	=> 'pf_change_details_settings_pre_style',
			'pre_image_display_fieldset'	=> 'pf_change_details_settings_pre_image_display_fieldset',
			'new_image_display_option'	=> 'pf_change_details_settings_new_image_display_option',
			'pre_image_display_fieldset_end'	=> 'pf_change_details_settings_pre_image_display_fieldset_end',
			'pre_show_sigs_checkbox'	=> 'pf_change_details_settings_pre_show_sigs_checkbox',
			'pre_display_fieldset_end'	=> 'pf_change_details_settings_pre_display_fieldset_end',
			'display_fieldset_end'	=> 'pf_change_details_settings_display_fieldset_end',
			'pre_pagination_fieldset'	=> 'pf_change_details_settings_pre_pagination_fieldset',
			'pre_disp_topics'	=> 'pf_change_details_settings_pre_disp_topics',
			'pre_disp_posts'	=> 'pf_change_details_settings_pre_disp_posts',
			'pre_pagination_fieldset_end'	=> 'pf_change_details_settings_pre_pagination_fieldset_end',
			'pagination_fieldset_end'	=> 'pf_change_details_settings_pagination_fieldset_end',
			'pre_email_fieldset'	=> 'pf_change_details_settings_pre_email_fieldset',
			'pre_email_settings_fieldset'	=> 'pf_change_details_settings_pre_email_settings_fieldset',
			'new_email_setting_option'	=> 'pf_change_details_settings_new_email_setting_option',
			'pre_email_settings_fieldset_end'	=> 'pf_change_details_settings_pre_email_settings_fieldset_end',
			'email_settings_fieldset_end'	=> 'pf_change_details_settings_email_settings_fieldset_end',
			'new_subscription_option'	=> 'pf_change_details_settings_new_subscription_option',
			'pre_subscription_fieldset_end'	=> 'pf_change_details_settings_pre_subscription_fieldset_end',
			'subscription_fieldset_end'	=> 'pf_change_details_settings_subscription_fieldset_end',
			'pre_email_fieldset_end'	=> 'pf_change_details_settings_pre_email_fieldset_end',
			'email_fieldset_end'	=> 'pf_change_details_settings_email_fieldset_end',
			'end'	=> 'pf_change_details_settings_end',
		),
		ProfileRendering::SIGNATURE	=> array(
			'output_start'	=> 'pf_change_details_signature_output_start',
			'pre_errors'	=> 'pf_change_details_signature_pre_errors',
			'pre_fieldset'	=> 'pf_change_details_signature_pre_fieldset',
			'pre_signature_demo'	=> 'pf_change_details_signature_pre_signature_demo',
			'pre_signature_text'	=> 'pf_change_details_signature_pre_signature_text',
			'pre_fieldset_end'	=> 'pf_change_details_signature_pre_fieldset_end',
			'fieldset_end'	=> 'pf_change_details_signature_fieldset_end',
			'end'	=> 'pf_change_details_signature_end',
		),
		ProfileRendering::AVATAR	=> array(
			'output_start'	=> 'pf_change_details_avatar_output_start',
			'pre_errors'	=> 'pf_change_details_avatar_pre_errors',
			'pre_fieldset'	=> 'pf_change_details_avatar_pre_fieldset',
			'pre_cur_avatar_info'	=> 'pf_change_details_avatar_pre_cur_avatar_info',
			'pre_avatar_upload'	=> 'pf_change_details_avatar_pre_avatar_upload',
			'pre_fieldset_end'	=> 'pf_change_details_avatar_pre_fieldset_end',
			'fieldset_end'	=> 'pf_change_details_avatar_fieldset_end',
			'end'	=> 'pf_change_details_avatar_end',
		),
		ProfileRendering::ADMIN	=> array(
			'output_start'	=> 'pf_change_details_admin_output_start',
			'pre_user_management'	=> 'pf_change_details_admin_pre_user_management',
			'pre_membership'	=> 'pf_change_details_admin_pre_membership',
			'pre_group_membership'	=> 'pf_change_details_admin_pre_group_membership',
			'pre_group_membership_submit'	=> 'pf_change_details_admin_pre_group_membership_submit',
			'pre_mod_assignment'	=> 'pf_change_details_admin_pre_mod_assignment',
			'pre_mod_assignment_fieldset'	=> 'pf_change_details_admin_pre_mod_assignment_fieldset',
			'pre_forum_checklist'	=> 'pf_change_details_admin_pre_forum_checklist',
			'pre_mod_assignment_fieldset_end'	=> 'pf_change_details_admin_pre_mod_assignment_fieldset_end',
			'mod_assignment_fieldset_end'	=> 'pf_change_details_admin_mod_assignment_fieldset_end',
			'form_end'	=> 'pf_change_details_admin_form_end',
			'end'	=> 'pf_change_details_admin_end',
		),
	);

	/** @var array<string, string> group => the key of $forum_page it is published under */
	private const KEYS = array(
		ProfileRendering::HIDDEN_FIELDS		=> 'hidden_fields',
		ProfileRendering::ERRORS			=> 'errors',
		ProfileRendering::TEXT_OPTIONS		=> 'text_options',
		ProfileRendering::INFO				=> 'frm_info',
		ProfileRendering::USER_OPTIONS		=> 'user_options',
		ProfileRendering::USER_IDENT		=> 'user_ident',
		ProfileRendering::USER_INFO			=> 'user_info',
		ProfileRendering::USER_CONTACT		=> 'user_contact',
		ProfileRendering::USER_ACTIVITY		=> 'user_activity',
		ProfileRendering::USER_PRIVATE		=> 'user_private',
		ProfileRendering::USER_MANAGEMENT	=> 'user_management',
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(ProfileRendering $event): void {
		$point = self::POINTS[$event->page()][$event->position()];
		if (!LegacyScope::attached($point))
			return;

		ProfileState::publish($event->user());

		foreach ($event->groups() as $group)
		{
			$parts = array();
			foreach ($event->names($group) as $name)
				$parts[$name] = (string) $event->entry($group, $name);

			ForumPage::set(self::KEYS[$group], $parts);
		}

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));

		foreach ($event->groups() as $group)
		{
			foreach ($event->names($group) as $name)
				$event->remove($group, $name);

			foreach (Markers::entries(ForumPage::get(self::KEYS[$group])) as $name => $markup)
				$event->set($group, (string) $name, $markup);
		}
	}
}
