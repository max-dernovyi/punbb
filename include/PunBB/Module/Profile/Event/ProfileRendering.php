<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\FormMarkup;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;

/**
 * A position on a page of a profile, which an observer may add markup at: the
 * forms changing a password or an address, deleting the member, each section,
 * and the profile shown to a visitor who may not edit it. Each page has its
 * positions, named after what follows them. A form numbers its field groups,
 * items and fields in order; markup that adds any of them counts them, and
 * the form numbers on from there. At some positions the named markup shown
 * next may still change: the hidden fields at the start of a form, the errors
 * before they are listed, and the lists of what the profile shows.
 */
final class ProfileRendering implements EventInterface {
	use FormMarkup;
	use PartsByName;

	public const CHANGE_PASS_KEY = 'change_pass_key';

	public const CHANGE_PASS = 'change_pass';

	public const CHANGE_EMAIL = 'change_email';

	public const DELETE_USER = 'delete_user';

	public const DETAILS = 'details';

	public const ABOUT = 'about';

	public const IDENTITY = 'identity';

	public const SETTINGS = 'settings';

	public const SIGNATURE = 'signature';

	public const AVATAR = 'avatar';

	public const ADMIN = 'admin';

	public const OUTPUT_START = 'output_start';

	public const END = 'end';

	/** @var array<string, list<string>> page => its positions, in the order the page is read */
	public const PAGES = array(
		self::CHANGE_PASS_KEY => array(
			'output_start', 'pre_errors', 'pre_fieldset', 'pre_new_password', 'pre_new_password_confirm', 'pre_fieldset_end', 'fieldset_end', 'end',
		),
		self::CHANGE_PASS => array(
			'output_start', 'pre_errors', 'pre_fieldset', 'pre_old_password', 'pre_new_password', 'pre_new_password_confirm', 'pre_fieldset_end',
			'fieldset_end', 'end',
		),
		self::CHANGE_EMAIL => array(
			'output_start', 'pre_errors', 'pre_fieldset', 'pre_new_email', 'pre_password', 'pre_fieldset_end', 'fieldset_end', 'end',
		),
		self::DELETE_USER => array(
			'output_start', 'pre_fieldset', 'pre_confirm_checkbox', 'pre_fieldset_end', 'fieldset_end', 'end',
		),
		self::DETAILS => array(
			'output_start', 'pre_user_info', 'pre_user_ident_info', 'pre_user_contact_info', 'pre_user_activity_info', 'pre_user_sig_info', 'user_info_end',
			'end',
		),
		self::ABOUT => array(
			'output_start', 'pre_user_info', 'pre_user_ident_info', 'pre_user_contact_info', 'pre_user_activity_info', 'pre_user_sig_info',
			'pre_user_private_info', 'user_info_end', 'end',
		),
		self::IDENTITY => array(
			'output_start', 'pre_errors', 'pre_req_info_fieldset', 'pre_username', 'pre_email', 'pre_req_info_fieldset_end', 'req_info_fieldset_end',
			'pre_personal_fieldset', 'pre_realname', 'pre_title', 'pre_location', 'pre_admin_note', 'pre_num_posts', 'pre_personal_fieldset_end',
			'personal_fieldset_end', 'pre_url', 'pre_facebook', 'pre_twitter', 'pre_linkedin', 'pre_jabber', 'pre_skype', 'pre_msn', 'pre_icq', 'pre_aim',
			'pre_yahoo', 'pre_contact_fieldset_end', 'contact_fieldset_end', 'end',
		),
		self::SETTINGS => array(
			'output_start', 'pre_local_fieldset', 'pre_language', 'pre_timezone', 'pre_dst_checkbox', 'pre_time_format', 'pre_date_format',
			'pre_local_fieldset_end', 'local_fieldset_end', 'pre_display_fieldset', 'pre_style', 'pre_image_display_fieldset', 'new_image_display_option',
			'pre_image_display_fieldset_end', 'pre_show_sigs_checkbox', 'pre_display_fieldset_end', 'display_fieldset_end', 'pre_pagination_fieldset',
			'pre_disp_topics', 'pre_disp_posts', 'pre_pagination_fieldset_end', 'pagination_fieldset_end', 'pre_email_fieldset',
			'pre_email_settings_fieldset', 'new_email_setting_option', 'pre_email_settings_fieldset_end', 'email_settings_fieldset_end',
			'new_subscription_option', 'pre_subscription_fieldset_end', 'subscription_fieldset_end', 'pre_email_fieldset_end', 'email_fieldset_end', 'end',
		),
		self::SIGNATURE => array(
			'output_start', 'pre_errors', 'pre_fieldset', 'pre_signature_demo', 'pre_signature_text', 'pre_fieldset_end', 'fieldset_end', 'end',
		),
		self::AVATAR => array(
			'output_start', 'pre_errors', 'pre_fieldset', 'pre_cur_avatar_info', 'pre_avatar_upload', 'pre_fieldset_end', 'fieldset_end', 'end',
		),
		self::ADMIN => array(
			'output_start', 'pre_user_management', 'pre_membership', 'pre_group_membership', 'pre_group_membership_submit', 'pre_mod_assignment',
			'pre_mod_assignment_fieldset', 'pre_forum_checklist', 'pre_mod_assignment_fieldset_end', 'mod_assignment_fieldset_end', 'form_end', 'end',
		),
	);

	public const HIDDEN_FIELDS = 'hidden_fields';

	public const ERRORS = 'errors';

	public const TEXT_OPTIONS = 'text_options';

	public const INFO = 'info';

	public const USER_OPTIONS = 'user_options';

	public const USER_IDENT = 'user_ident';

	public const USER_INFO = 'user_info';

	public const USER_CONTACT = 'user_contact';

	public const USER_ACTIVITY = 'user_activity';

	public const USER_PRIVATE = 'user_private';

	public const USER_MANAGEMENT = 'user_management';

	/** @var array<string, array<string, list<string>>> page => position => the named markup that may still change there */
	public const PARTS = array(
		self::CHANGE_PASS_KEY => array(
			'pre_errors' => array(self::ERRORS),
		),
		self::CHANGE_PASS => array(
			'output_start' => array(self::HIDDEN_FIELDS),
			'pre_errors' => array(self::ERRORS),
		),
		self::CHANGE_EMAIL => array(
			'output_start' => array(self::HIDDEN_FIELDS),
			'pre_errors' => array(self::ERRORS),
		),
		self::DETAILS => array(
			'pre_user_ident_info' => array(self::USER_IDENT, self::USER_INFO),
			'pre_user_contact_info' => array(self::USER_CONTACT),
			'pre_user_activity_info' => array(self::USER_ACTIVITY),
		),
		self::ABOUT => array(
			'output_start' => array(self::USER_OPTIONS),
			'pre_user_ident_info' => array(self::USER_IDENT, self::USER_INFO),
			'pre_user_contact_info' => array(self::USER_CONTACT),
			'pre_user_activity_info' => array(self::USER_ACTIVITY),
			'pre_user_private_info' => array(self::USER_PRIVATE),
		),
		self::IDENTITY => array(
			'output_start' => array(self::HIDDEN_FIELDS),
			'pre_errors' => array(self::ERRORS),
		),
		self::SETTINGS => array(
			'output_start' => array(self::HIDDEN_FIELDS),
		),
		self::SIGNATURE => array(
			'output_start' => array(self::HIDDEN_FIELDS, self::TEXT_OPTIONS),
			'pre_errors' => array(self::ERRORS),
		),
		self::AVATAR => array(
			'output_start' => array(self::HIDDEN_FIELDS),
			'pre_errors' => array(self::ERRORS),
			'pre_fieldset' => array(self::INFO),
		),
		self::ADMIN => array(
			'output_start' => array(self::HIDDEN_FIELDS),
			'pre_user_management' => array(self::USER_MANAGEMENT),
		),
	);

	private string $page;

	private string $position;

	/**
	 * @param array<string, Parts> $parts group => the named markup it carries, among those PARTS allows here
	 */
	public function __construct(
		string $page,
		string $position,
		private readonly ProfileUserInterface $user,
		int $groupCount = 0,
		int $itemCount = 0,
		int $fieldCount = 0,
		array $parts = array()
	) {
		if (!in_array($position, self::PAGES[$page] ?? array(), true))
			throw new InvalidArgumentException(sprintf('The profile\'s page "%s" has no position "%s"', $page, $position));

		$this->page = $page;
		$this->position = $position;

		foreach (self::PARTS[$page][$position] ?? array() as $group)
			$this->parts[$group] = $parts[$group] ?? new Parts();

		$this->count($groupCount, $itemCount, $fieldCount);
	}

	public function page(): string {
		return $this->page;
	}

	public function position(): string {
		return $this->position;
	}

	public function user(): ProfileUserInterface {
		return $this->user;
	}

	/** @return list<string> the groups of named markup the position carries */
	public function groups(): array {
		return array_map(strval(...), array_keys($this->parts));
	}
}
