<?php

declare(strict_types=1);

namespace PunBB\Module\Settings\View;

use PunBB\Module\Layout\View\Html;
use PunBB\Module\Settings\Event\SettingsFormRendering;

/**
 * A section's form as its template shows it, built in the order the form is
 * read: the markup placed at each position, and the numbers the form gives its
 * field groups, items and fields after each position.
 */
final class FormView {
	/**
	 * What each section numbers after a position, by the name its template
	 * reads the number under: 'group:' a field group, 'item:' an item, 'field:'
	 * a field; 'restart' numbers the groups and items that follow from one again.
	 *
	 * @var array<string, array<string, list<string>>>
	 */
	private const PLANS = array(
		'setup' => array(
			'pre_personal_fieldset'		=> array('group:g1'),
			'pre_board_title'			=> array('item:i1', 'field:board_title'),
			'pre_board_descrip'			=> array('item:i2', 'field:board_desc'),
			'pre_default_style'			=> array('item:i3', 'field:default_style'),
			'personal_fieldset_end'		=> array('restart'),
			'pre_local_fieldset'		=> array('group:g2'),
			'pre_default_language'		=> array('item:i4', 'field:default_lang'),
			'pre_default_timezone'		=> array('item:i5', 'field:default_timezone'),
			'pre_default_dst'			=> array('item:i6', 'field:default_dst'),
			'pre_time_format'			=> array('item:i7', 'field:time_format'),
			'pre_date_format'			=> array('item:i8', 'field:date_format'),
			'local_fieldset_end'		=> array('restart'),
			'pre_timeouts_fieldset'		=> array('group:g3'),
			'pre_visit_timeout'			=> array('item:i9', 'field:timeout_visit'),
			'pre_online_timeout'		=> array('item:i10', 'field:timeout_online'),
			'pre_redirect_time'			=> array('item:i11', 'field:redirect_delay'),
			'timeouts_fieldset_end'		=> array('restart'),
			'pre_pagination_fieldset'	=> array('group:g4'),
			'pre_topics_per_page'		=> array('item:i12', 'field:disp_topics_default'),
			'pre_posts_per_page'		=> array('item:i13', 'field:disp_posts_default'),
			'pre_topic_review'			=> array('item:i14', 'field:topic_review'),
			'pagination_fieldset_end'	=> array('restart'),
			'pre_reports_fieldset'		=> array('group:g5', 'item:i15', 'field:report_method_0', 'field:report_method_1', 'field:report_method_2'),
			'reports_fieldset_end'		=> array('restart'),
			'pre_url_scheme_fieldset'	=> array('group:g6'),
			'pre_url_scheme'			=> array('item:i16', 'field:sef'),
			'url_scheme_fieldset_end'	=> array('restart'),
			'pre_links_fieldset'		=> array('group:g7'),
			'pre_additional_navlinks'	=> array('item:i17', 'field:additional_navlinks'),
		),
		'features' => array(
			'pre_general_fieldset'				=> array('group:g1'),
			'pre_search_all_checkbox'			=> array('item:i1', 'field:search_all_forums'),
			'pre_ranks_checkbox'				=> array('item:i2', 'field:ranks'),
			'pre_censoring_checkbox'			=> array('item:i3', 'field:censoring'),
			'pre_quickjump_checkbox'			=> array('item:i4', 'field:quickjump'),
			'pre_show_version_checkbox'			=> array('item:i5', 'field:show_version'),
			'pre_show_moderators_checkbox'		=> array('item:i6', 'field:show_moderators'),
			'pre_users_online_checkbox'			=> array('item:i7', 'field:users_online'),
			'general_fieldset_end'				=> array('restart'),
			'pre_posting_fieldset'				=> array('group:g2'),
			'pre_quickpost_checkbox'			=> array('item:i8', 'field:quickpost'),
			'pre_subscriptions_checkbox'		=> array('item:i9', 'field:subscriptions'),
			'pre_force_guest_email_checkbox'	=> array('item:i10', 'field:force_guest_email'),
			'pre_show_dot_checkbox'				=> array('item:i11', 'field:show_dot'),
			'pre_topic_views_checkbox'			=> array('item:i12', 'field:topic_views'),
			'pre_show_post_count_checkbox'		=> array('item:i13', 'field:show_post_count'),
			'pre_show_user_info_checkbox'		=> array('item:i14', 'field:show_user_info'),
			'posting_fieldset_end'				=> array('restart'),
			'pre_message_fieldset'				=> array('group:g3'),
			'pre_message_content_fieldset'		=> array('item:i15', 'field:message_bbcode', 'field:message_img_tag', 'field:smilies', 'field:make_links'),
			'message_content_fieldset_end'		=> array('item:i16', 'field:message_all_caps', 'field:subject_all_caps'),
			'message_caps_fieldset_end'			=> array('item:i17', 'field:indent_num_spaces'),
			'pre_quote_depth'					=> array('item:i18', 'field:quote_depth'),
			'message_fieldset_end'				=> array('restart'),
			'pre_sig_fieldset'					=> array('group:g4'),
			'pre_signature_checkbox'			=> array('item:i19', 'field:signatures'),
			'pre_sig_content_fieldset'			=> array('item:i20', 'field:sig_bbcode', 'field:sig_img_tag', 'field:smilies_sig'),
			'sig_content_fieldset_end'			=> array('item:i21', 'field:sig_all_caps', 'item:i22', 'field:sig_length'),
			'pre_max_sig_lines'					=> array('item:i23', 'field:sig_lines'),
			'sig_fieldset_end'					=> array('restart'),
			'pre_avatars_fieldset'				=> array('group:g5'),
			'pre_avatar_checkbox'				=> array('item:i24', 'field:avatars'),
			'pre_avatar_directory'				=> array('item:i25', 'field:avatars_dir'),
			'pre_avatar_max_width'				=> array('item:i26', 'field:avatars_width'),
			'pre_avatar_max_height'				=> array('item:i27', 'field:avatars_height'),
			'pre_avatar_max_size'				=> array('item:i28', 'field:avatars_size'),
			'avatars_fieldset_end'				=> array('restart'),
			'pre_updates_fieldset'				=> array('group:g6'),
			'pre_updates_checkbox'				=> array('item:i29', 'field:check_for_updates'),
			'pre_version_updates_checkbox'		=> array('item:i30', 'field:check_for_versions'),
			'updates_fieldset_end'				=> array('restart'),
			'post_updates_disabled_box'			=> array('restart'),
			'pre_mask_passwords_fieldset'		=> array('group:g7'),
			'pre_mask_passwords_checkbox'		=> array('item:i31', 'field:mask_passwords'),
			'mask_passwords_fieldset_end'		=> array('restart'),
			'pre_gzip_fieldset'					=> array('group:g8'),
			'pre_gzip_checkbox'					=> array('item:i32', 'field:gzip'),
		),
		'email' => array(
			'pre_addresses_fieldset'	=> array('group:g1'),
			'pre_admin_email'			=> array('item:i1', 'field:admin_email'),
			'pre_webmaster_email'		=> array('item:i2', 'field:webmaster_email'),
			'pre_mailing_list'			=> array('item:i3', 'field:mailing_list'),
			'addresses_fieldset_end'	=> array('restart'),
			'pre_smtp_fieldset'			=> array('group:g2'),
			'pre_smtp_host'				=> array('item:i4', 'field:smtp_host'),
			'pre_smtp_user'				=> array('item:i5', 'field:smtp_user'),
			'pre_smtp_pass'				=> array('item:i6', 'field:smtp_pass'),
			'pre_smtp_ssl'				=> array('item:i7', 'field:smtp_ssl'),
		),
		'announcements' => array(
			'pre_announcement_fieldset'			=> array('group:g1'),
			'pre_enable_announcement_checkbox'	=> array('item:i1', 'field:announcement'),
			'pre_announcement_heading'			=> array('item:i2', 'field:announcement_heading'),
			'pre_announcement_message'			=> array('item:i3', 'field:announcement_message'),
		),
		'registration' => array(
			'pre_new_regs_fieldset'			=> array('group:g1'),
			'pre_allow_new_regs_checkbox'	=> array('item:i1', 'field:regs_allow'),
			'pre_verify_regs_checkbox'		=> array('item:i2', 'field:regs_verify'),
			'pre_email_fieldset'			=> array('item:i3', 'field:allow_banned_email', 'field:allow_dupe_email'),
			'email_fieldset_end'			=> array('item:i4', 'field:regs_report'),
			'pre_email_setting_fieldset'	=> array('item:i5', 'field:default_email_setting_0', 'field:default_email_setting_1', 'field:default_email_setting_2'),
			'new_regs_fieldset_end'			=> array('restart'),
			'pre_rules_fieldset'			=> array('group:g2'),
			'pre_rules_checkbox'			=> array('item:i6', 'field:rules'),
			'pre_rules_text'				=> array('item:i7', 'field:rules_message'),
		),
		'maintenance' => array(
			'pre_maintenance_fieldset'	=> array('group:g1'),
			'pre_maintenance_checkbox'	=> array('item:i1', 'field:maintenance'),
			'pre_maintenance_message'	=> array('item:i2', 'field:maintenance_message'),
		),
	);

	/** @var array<string, Html> position => markup */
	private array $positions;

	/** @var array<string, int> what the form numbers => its number */
	private array $numbers = array();

	private int $groupCount = 0;

	private int $itemCount = 0;

	private int $fieldCount = 0;

	/** @param array<string, mixed> $values what the form shows */
	public function __construct(private readonly string $section, private array $values) {
		$this->positions = array_fill_keys(SettingsFormRendering::SECTIONS[$section], new Html(''));
	}

	/** @return array{int, int, int} the group, item and field counts so far */
	public function counts(): array {
		return array($this->groupCount, $this->itemCount, $this->fieldCount);
	}

	/**
	 * Places what the observers of $event added, counts on from where they left
	 * the form, and numbers what follows the position.
	 */
	public function place(SettingsFormRendering $event): Html {
		$this->groupCount = $event->groupCount();
		$this->itemCount = $event->itemCount();
		$this->fieldCount = $event->fieldCount();

		foreach (self::PLANS[$this->section][$event->position()] ?? array() as $number)
		{
			[$kind, $name] = explode(':', $number.':');

			match ($kind) {
				'group'	=> $this->numbers[$name] = ++$this->groupCount,
				'item'	=> $this->numbers[$name] = ++$this->itemCount,
				'field'	=> $this->numbers[$name] = ++$this->fieldCount,
				default	=> $this->groupCount = $this->itemCount = 0,
			};
		}

		return $this->positions[$event->position()] = new Html($event->markup());
	}

	/** @return array<string, mixed> the template's variables */
	public function variables(): array {
		return array('positions' => $this->positions, 'numbers' => $this->numbers) + $this->values;
	}
}
