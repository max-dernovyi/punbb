<?php

declare(strict_types=1);

namespace PunBB\Module\Settings\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\FormMarkup;

/**
 * A position in a section's form, which an observer may add markup at. Each
 * section has its positions, named after the field or field group that
 * follows them, from its start to its end. The form numbers its field groups,
 * items and fields in order, each heading numbering its groups and items from
 * one again; the features section has either the update checks or the box
 * saying they cannot run.
 */
final class SettingsFormRendering implements EventInterface {
	use FormMarkup;

	public const OUTPUT_START = 'output_start';

	public const END = 'end';

	/** @var array<string, list<string>> section => its positions, in the order the form is read */
	public const SECTIONS = array(
		'setup' => array(
			'output_start', 'pre_personal_fieldset', 'pre_board_title', 'pre_board_descrip', 'pre_default_style', 'pre_personal_fieldset_end',
			'personal_fieldset_end', 'pre_local_fieldset', 'pre_default_language', 'pre_default_timezone', 'pre_default_dst', 'pre_time_format',
			'pre_date_format', 'pre_local_fieldset_end', 'local_fieldset_end', 'pre_timeouts_fieldset', 'pre_visit_timeout', 'pre_online_timeout',
			'pre_redirect_time', 'pre_timeouts_fieldset_end', 'timeouts_fieldset_end', 'pre_pagination_fieldset', 'pre_topics_per_page', 'pre_posts_per_page',
			'pre_topic_review', 'pre_pagination_fieldset_end', 'pagination_fieldset_end', 'pre_reports_fieldset', 'new_reporting_method',
			'pre_reports_fieldset_end', 'reports_fieldset_end', 'pre_url_scheme_fieldset', 'pre_url_scheme', 'pre_url_scheme_fieldset_end',
			'url_scheme_fieldset_end', 'pre_links_fieldset', 'pre_additional_navlinks', 'pre_links_fieldset_end', 'links_fieldset_end', 'end',
		),
		'features' => array(
			'output_start', 'pre_general_fieldset', 'pre_search_all_checkbox', 'pre_ranks_checkbox', 'pre_censoring_checkbox', 'pre_quickjump_checkbox',
			'pre_show_version_checkbox', 'pre_show_moderators_checkbox', 'pre_users_online_checkbox', 'pre_general_fieldset_end', 'general_fieldset_end',
			'pre_posting_fieldset', 'pre_quickpost_checkbox', 'pre_subscriptions_checkbox', 'pre_force_guest_email_checkbox', 'pre_show_dot_checkbox',
			'pre_topic_views_checkbox', 'pre_show_post_count_checkbox', 'pre_show_user_info_checkbox', 'pre_posting_fieldset_end', 'posting_fieldset_end',
			'pre_message_fieldset', 'pre_message_content_fieldset', 'new_message_content_option', 'pre_message_content_fieldset_end',
			'message_content_fieldset_end', 'new_message_caps_option', 'pre_message_caps_fieldset_end', 'message_caps_fieldset_end', 'pre_quote_depth',
			'pre_message_fieldset_end', 'message_fieldset_end', 'pre_sig_fieldset', 'pre_signature_checkbox', 'pre_sig_content_fieldset',
			'new_sig_content_option', 'pre_sig_content_fieldset_end', 'sig_content_fieldset_end', 'pre_max_sig_lines', 'pre_sig_fieldset_end',
			'sig_fieldset_end', 'pre_avatars_fieldset', 'pre_avatar_checkbox', 'pre_avatar_directory', 'pre_avatar_max_width', 'pre_avatar_max_height',
			'pre_avatar_max_size', 'pre_avatars_fieldset_end', 'avatars_fieldset_end', 'pre_updates_fieldset', 'pre_updates_checkbox',
			'pre_version_updates_checkbox', 'pre_updates_fieldset_end', 'updates_fieldset_end', 'post_updates_disabled_box', 'pre_mask_passwords_fieldset',
			'pre_mask_passwords_checkbox', 'pre_mask_passwords_fieldset_end', 'mask_passwords_fieldset_end', 'pre_gzip_fieldset', 'pre_gzip_checkbox',
			'pre_gzip_fieldset_end', 'gzip_fieldset_end', 'end',
		),
		'email' => array(
			'output_start', 'pre_addresses_fieldset', 'pre_admin_email', 'pre_webmaster_email', 'pre_mailing_list', 'pre_addresses_fieldset_end',
			'addresses_fieldset_end', 'pre_smtp_fieldset', 'pre_smtp_host', 'pre_smtp_user', 'pre_smtp_pass', 'pre_smtp_ssl', 'pre_smtp_fieldset_end',
			'smtp_fieldset_end', 'end',
		),
		'announcements' => array(
			'output_start', 'pre_announcement_fieldset', 'pre_enable_announcement_checkbox', 'pre_announcement_heading', 'pre_announcement_message',
			'pre_announcement_fieldset_end', 'announcement_fieldset_end', 'end',
		),
		'registration' => array(
			'output_start', 'pre_new_regs_fieldset', 'pre_allow_new_regs_checkbox', 'pre_verify_regs_checkbox', 'pre_email_fieldset', 'new_email_option',
			'pre_email_fieldset_end', 'email_fieldset_end', 'pre_email_setting_fieldset', 'new_email_setting_option', 'pre_email_setting_fieldset_end',
			'email_setting_fieldset_end', 'new_regs_fieldset_end', 'pre_rules_fieldset', 'pre_rules_checkbox', 'pre_rules_text', 'pre_rules_fieldset_end',
			'rules_fieldset_end', 'end',
		),
		'maintenance' => array(
			'output_start', 'pre_maintenance_fieldset', 'pre_maintenance_checkbox', 'pre_maintenance_message', 'pre_maintenance_fieldset_end',
			'maintenance_fieldset_end', 'end',
		),
	);

	public function __construct(private readonly string $section, private readonly string $position, int $groupCount, int $itemCount, int $fieldCount) {
		if (!in_array($position, self::SECTIONS[$section] ?? array(), true))
			throw new InvalidArgumentException(sprintf('The settings section "%s" has no position "%s"', $section, $position));

		$this->count($groupCount, $itemCount, $fieldCount);
	}

	public function section(): string {
		return $this->section;
	}

	public function position(): string {
		return $this->position;
	}
}
