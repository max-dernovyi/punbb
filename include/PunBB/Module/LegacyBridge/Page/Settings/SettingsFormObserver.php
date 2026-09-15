<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Settings;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Page\ForumPage;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Settings\Event\SettingsFormRendering;

/**
 * Renders the points of a section's form at their positions, with the
 * section as $section and the form's counts in $forum_page, read back for the
 * fields that follow. Every section ends at aop_end.
 */
final class SettingsFormObserver {
	/** @var array<string, array<string, string>> section => position => the point there */
	public const POINTS = array(
		'setup' => array(
			'output_start'					=> 'aop_setup_output_start',
			'pre_personal_fieldset'			=> 'aop_setup_pre_personal_fieldset',
			'pre_board_title'				=> 'aop_setup_pre_board_title',
			'pre_board_descrip'				=> 'aop_setup_pre_board_descrip',
			'pre_default_style'				=> 'aop_setup_pre_default_style',
			'pre_personal_fieldset_end'		=> 'aop_setup_pre_personal_fieldset_end',
			'personal_fieldset_end'			=> 'aop_setup_personal_fieldset_end',
			'pre_local_fieldset'			=> 'aop_setup_pre_local_fieldset',
			'pre_default_language'			=> 'aop_setup_pre_default_language',
			'pre_default_timezone'			=> 'aop_setup_pre_default_timezone',
			'pre_default_dst'				=> 'aop_setup_pre_default_dst',
			'pre_time_format'				=> 'aop_setup_pre_time_format',
			'pre_date_format'				=> 'aop_setup_pre_date_format',
			'pre_local_fieldset_end'		=> 'aop_setup_pre_local_fieldset_end',
			'local_fieldset_end'			=> 'aop_setup_local_fieldset_end',
			'pre_timeouts_fieldset'			=> 'aop_setup_pre_timeouts_fieldset',
			'pre_visit_timeout'				=> 'aop_setup_pre_visit_timeout',
			'pre_online_timeout'			=> 'aop_setup_pre_online_timeout',
			'pre_redirect_time'				=> 'aop_setup_pre_redirect_time',
			'pre_timeouts_fieldset_end'		=> 'aop_setup_pre_timeouts_fieldset_end',
			'timeouts_fieldset_end'			=> 'aop_setup_timeouts_fieldset_end',
			'pre_pagination_fieldset'		=> 'aop_setup_pre_pagination_fieldset',
			'pre_topics_per_page'			=> 'aop_setup_pre_topics_per_page',
			'pre_posts_per_page'			=> 'aop_setup_pre_posts_per_page',
			'pre_topic_review'				=> 'aop_setup_pre_topic_review',
			'pre_pagination_fieldset_end'	=> 'aop_setup_pre_pagination_fieldset_end',
			'pagination_fieldset_end'		=> 'aop_setup_pagination_fieldset_end',
			'pre_reports_fieldset'			=> 'aop_setup_pre_reports_fieldset',
			'new_reporting_method'			=> 'aop_setup_new_reporting_method',
			'pre_reports_fieldset_end'		=> 'aop_setup_pre_reports_fieldset_end',
			'reports_fieldset_end'			=> 'aop_setup_reports_fieldset_end',
			'pre_url_scheme_fieldset'		=> 'aop_setup_pre_url_scheme_fieldset',
			'pre_url_scheme'				=> 'aop_setup_pre_url_scheme',
			'pre_url_scheme_fieldset_end'	=> 'aop_setup_pre_url_scheme_fieldset_end',
			'url_scheme_fieldset_end'		=> 'aop_setup_url_scheme_fieldset_end',
			'pre_links_fieldset'			=> 'aop_setup_pre_links_fieldset',
			'pre_additional_navlinks'		=> 'aop_setup_pre_additional_navlinks',
			'pre_links_fieldset_end'		=> 'aop_setup_pre_links_fieldset_end',
			'links_fieldset_end'			=> 'aop_setup_links_fieldset_end',
			'end'							=> 'aop_end',
		),
		'features' => array(
			'output_start'						=> 'aop_features_output_start',
			'pre_general_fieldset'				=> 'aop_features_pre_general_fieldset',
			'pre_search_all_checkbox'			=> 'aop_features_pre_search_all_checkbox',
			'pre_ranks_checkbox'				=> 'aop_features_pre_ranks_checkbox',
			'pre_censoring_checkbox'			=> 'aop_features_pre_censoring_checkbox',
			'pre_quickjump_checkbox'			=> 'aop_features_pre_quickjump_checkbox',
			'pre_show_version_checkbox'			=> 'aop_features_pre_show_version_checkbox',
			'pre_show_moderators_checkbox'		=> 'aop_features_pre_show_moderators_checkbox',
			'pre_users_online_checkbox'			=> 'aop_features_pre_users_online_checkbox',
			'pre_general_fieldset_end'			=> 'aop_features_pre_general_fieldset_end',
			'general_fieldset_end'				=> 'aop_features_general_fieldset_end',
			'pre_posting_fieldset'				=> 'aop_features_pre_posting_fieldset',
			'pre_quickpost_checkbox'			=> 'aop_features_pre_quickpost_checkbox',
			'pre_subscriptions_checkbox'		=> 'aop_features_pre_subscriptions_checkbox',
			'pre_force_guest_email_checkbox'	=> 'aop_features_pre_force_guest_email_checkbox',
			'pre_show_dot_checkbox'				=> 'aop_features_pre_show_dot_checkbox',
			'pre_topic_views_checkbox'			=> 'aop_features_pre_topic_views_checkbox',
			'pre_show_post_count_checkbox'		=> 'aop_features_pre_show_post_count_checkbox',
			'pre_show_user_info_checkbox'		=> 'aop_features_pre_show_user_info_checkbox',
			'pre_posting_fieldset_end'			=> 'aop_features_pre_posting_fieldset_end',
			'posting_fieldset_end'				=> 'aop_features_posting_fieldset_end',
			'pre_message_fieldset'				=> 'aop_features_pre_message_fieldset',
			'pre_message_content_fieldset'		=> 'aop_features_pre_message_content_fieldset',
			'new_message_content_option'		=> 'aop_features_new_message_content_option',
			'pre_message_content_fieldset_end'	=> 'aop_features_pre_message_content_fieldset_end',
			'message_content_fieldset_end'		=> 'aop_features_message_content_fieldset_end',
			'new_message_caps_option'			=> 'aop_features_new_message_caps_option',
			'pre_message_caps_fieldset_end'		=> 'aop_features_pre_message_caps_fieldset_end',
			'message_caps_fieldset_end'			=> 'aop_features_message_caps_fieldset_end',
			'pre_quote_depth'					=> 'aop_features_pre_quote_depth',
			'pre_message_fieldset_end'			=> 'aop_features_pre_message_fieldset_end',
			'message_fieldset_end'				=> 'aop_features_message_fieldset_end',
			'pre_sig_fieldset'					=> 'aop_features_pre_sig_fieldset',
			'pre_signature_checkbox'			=> 'aop_features_pre_signature_checkbox',
			'pre_sig_content_fieldset'			=> 'aop_features_pre_sig_content_fieldset',
			'new_sig_content_option'			=> 'aop_features_new_sig_content_option',
			'pre_sig_content_fieldset_end'		=> 'aop_features_pre_sig_content_fieldset_end',
			'sig_content_fieldset_end'			=> 'aop_features_sig_content_fieldset_end',
			'pre_max_sig_lines'					=> 'aop_features_pre_max_sig_lines',
			'pre_sig_fieldset_end'				=> 'aop_features_pre_sig_fieldset_end',
			'sig_fieldset_end'					=> 'aop_features_sig_fieldset_end',
			'pre_avatars_fieldset'				=> 'aop_features_pre_avatars_fieldset',
			'pre_avatar_checkbox'				=> 'aop_features_pre_avatar_checkbox',
			'pre_avatar_directory'				=> 'aop_features_pre_avatar_directory',
			'pre_avatar_max_width'				=> 'aop_features_pre_avatar_max_width',
			'pre_avatar_max_height'				=> 'aop_features_pre_avatar_max_height',
			'pre_avatar_max_size'				=> 'aop_features_pre_avatar_max_size',
			'pre_avatars_fieldset_end'			=> 'aop_features_pre_avatars_fieldset_end',
			'avatars_fieldset_end'				=> 'aop_features_avatars_fieldset_end',
			'pre_updates_fieldset'				=> 'aop_features_pre_updates_fieldset',
			'pre_updates_checkbox'				=> 'aop_features_pre_updates_checkbox',
			'pre_version_updates_checkbox'		=> 'aop_features_pre_version_updates_checkbox',
			'pre_updates_fieldset_end'			=> 'aop_features_pre_updates_fieldset_end',
			'updates_fieldset_end'				=> 'aop_features_updates_fieldset_end',
			'post_updates_disabled_box'			=> 'aop_features_post_updates_disabled_box',
			'pre_mask_passwords_fieldset'		=> 'aop_features_pre_mask_passwords_fieldset',
			'pre_mask_passwords_checkbox'		=> 'aop_features_pre_mask_passwords_checkbox',
			'pre_mask_passwords_fieldset_end'	=> 'aop_features_pre_mask_passwords_fieldset_end',
			'mask_passwords_fieldset_end'		=> 'aop_features_mask_passwords_fieldset_end',
			'pre_gzip_fieldset'					=> 'aop_features_pre_gzip_fieldset',
			'pre_gzip_checkbox'					=> 'aop_features_pre_gzip_checkbox',
			'pre_gzip_fieldset_end'				=> 'aop_features_pre_gzip_fieldset_end',
			'gzip_fieldset_end'					=> 'aop_features_gzip_fieldset_end',
			'end'								=> 'aop_end',
		),
		'email' => array(
			'output_start'					=> 'aop_email_output_start',
			'pre_addresses_fieldset'		=> 'aop_email_pre_addresses_fieldset',
			'pre_admin_email'				=> 'aop_email_pre_admin_email',
			'pre_webmaster_email'			=> 'aop_email_pre_webmaster_email',
			'pre_mailing_list'				=> 'aop_email_pre_mailing_list',
			'pre_addresses_fieldset_end'	=> 'aop_email_pre_addresses_fieldset_end',
			'addresses_fieldset_end'		=> 'aop_email_addresses_fieldset_end',
			'pre_smtp_fieldset'				=> 'aop_email_pre_smtp_fieldset',
			'pre_smtp_host'					=> 'aop_email_pre_smtp_host',
			'pre_smtp_user'					=> 'aop_email_pre_smtp_user',
			'pre_smtp_pass'					=> 'aop_email_pre_smtp_pass',
			'pre_smtp_ssl'					=> 'aop_email_pre_smtp_ssl',
			'pre_smtp_fieldset_end'			=> 'aop_email_pre_smtp_fieldset_end',
			'smtp_fieldset_end'				=> 'aop_email_smtp_fieldset_end',
			'end'							=> 'aop_end',
		),
		'announcements' => array(
			'output_start'						=> 'aop_announcements_output_start',
			'pre_announcement_fieldset'			=> 'aop_announcements_pre_announcement_fieldset',
			'pre_enable_announcement_checkbox'	=> 'aop_announcements_pre_enable_announcement_checkbox',
			'pre_announcement_heading'			=> 'aop_announcements_pre_announcement_heading',
			'pre_announcement_message'			=> 'aop_announcements_pre_announcement_message',
			'pre_announcement_fieldset_end'		=> 'aop_announcements_pre_announcement_fieldset_end',
			'announcement_fieldset_end'			=> 'aop_announcements_announcement_fieldset_end',
			'end'								=> 'aop_end',
		),
		'registration' => array(
			'output_start'						=> 'aop_registration_output_start',
			'pre_new_regs_fieldset'				=> 'aop_registration_pre_new_regs_fieldset',
			'pre_allow_new_regs_checkbox'		=> 'aop_registration_pre_allow_new_regs_checkbox',
			'pre_verify_regs_checkbox'			=> 'aop_registration_pre_verify_regs_checkbox',
			'pre_email_fieldset'				=> 'aop_registration_pre_email_fieldset',
			'new_email_option'					=> 'aop_registration_new_email_option',
			'pre_email_fieldset_end'			=> 'aop_registration_pre_email_fieldset_end',
			'email_fieldset_end'				=> 'aop_registration_email_fieldset_end',
			'pre_email_setting_fieldset'		=> 'aop_registration_pre_email_setting_fieldset',
			'new_email_setting_option'			=> 'aop_registration_new_email_setting_option',
			'pre_email_setting_fieldset_end'	=> 'aop_registration_pre_email_setting_fieldset_end',
			'email_setting_fieldset_end'		=> 'aop_registration_email_setting_fieldset_end',
			'new_regs_fieldset_end'				=> 'aop_registration_new_regs_fieldset_end',
			'pre_rules_fieldset'				=> 'aop_registration_pre_rules_fieldset',
			'pre_rules_checkbox'				=> 'aop_registration_pre_rules_checkbox',
			'pre_rules_text'					=> 'aop_registration_pre_rules_text',
			'pre_rules_fieldset_end'			=> 'aop_registration_pre_rules_fieldset_end',
			'rules_fieldset_end'				=> 'aop_registration_rules_fieldset_end',
			'end'								=> 'aop_end',
		),
		'maintenance' => array(
			'output_start'					=> 'aop_maintenance_output_start',
			'pre_maintenance_fieldset'		=> 'aop_maintenance_pre_maintenance_fieldset',
			'pre_maintenance_checkbox'		=> 'aop_maintenance_pre_maintenance_checkbox',
			'pre_maintenance_message'		=> 'aop_maintenance_pre_maintenance_message',
			'pre_maintenance_fieldset_end'	=> 'aop_maintenance_pre_maintenance_fieldset_end',
			'maintenance_fieldset_end'		=> 'aop_maintenance_maintenance_fieldset_end',
			'end'							=> 'aop_end',
		),
	);

	public function __construct(private readonly PageScope $scope) {}

	public function observe(SettingsFormRendering $event): void {
		$point = self::POINTS[$event->section()][$event->position()];
		if (!LegacyScope::attached($point))
			return;

		$GLOBALS['section'] = $event->section();

		ForumPage::publishCounts($event->groupCount(), $event->itemCount(), $event->fieldCount());

		$event->append($this->scope->renderObserved($point, $event));

		$event->count(...ForumPage::counts($event->groupCount(), $event->itemCount(), $event->fieldCount()));
	}
}
