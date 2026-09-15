<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Groups\Api\Data\GroupInterface;
use PunBB\Module\Layout\Event\FormMarkup;

/**
 * A position in the form adding or editing a group, which an observer may add
 * markup at. The titles are there for every group; the permissions and flood
 * intervals for every group but the administrators, the moderation and the
 * guests' missing permissions as the group allows. The form numbers its field
 * groups, items and fields in order; the permissions and the flood intervals
 * number their groups and items from one again.
 */
final class GroupFormRendering implements EventInterface {
	use FormMarkup;

	public const OUTPUT_START = 'output_start';

	public const PRE_BASIC_DETAILS_FIELDSET = 'pre_basic_details_fieldset';

	public const PRE_GROUP_TITLE = 'pre_group_title';

	public const PRE_USER_TITLE = 'pre_user_title';

	public const PRE_BASIC_DETAILS_FIELDSET_END = 'pre_basic_details_fieldset_end';

	/** After the titles; the last position the administrators' form has before its end. */
	public const BASIC_DETAILS_FIELDSET_END = 'basic_details_fieldset_end';

	public const PRE_PERMISSIONS_FIELDSET = 'pre_permissions_fieldset';

	/** Before the moderation, which the guests and the default group lack. */
	public const PRE_MOD_PERMISSIONS_FIELDSET = 'pre_mod_permissions_fieldset';

	public const PRE_ALLOW_MODERATE_CHECKBOX = 'pre_allow_moderate_checkbox';

	public const PRE_ALLOW_MOD_EDIT_PROFILES_CHECKBOX = 'pre_allow_mod_edit_profiles_checkbox';

	public const PRE_ALLOW_MOD_EDIT_USERBANE_CHECKBOX = 'pre_allow_mod_edit_userbane_checkbox';

	public const PRE_ALLOW_MOD_CHANGE_PASS_CHECKBOX = 'pre_allow_mod_change_pass_checkbox';

	public const PRE_ALLOW_MOD_BAN_USERS_CHECKBOX = 'pre_allow_mod_ban_users_checkbox';

	public const PRE_MOD_PERMISSIONS_FIELDSET_END = 'pre_mod_permissions_fieldset_end';

	public const MOD_PERMISSIONS_FIELDSET_END = 'mod_permissions_fieldset_end';

	public const PRE_ALLOW_READ_BOARD_CHECKBOX = 'pre_allow_read_board_checkbox';

	public const PRE_ALLOW_VIEW_USERS_CHECKBOX = 'pre_allow_view_users_checkbox';

	public const PRE_ALLOW_POST_REPLIES_CHECKBOX = 'pre_allow_post_replies_checkbox';

	public const PRE_ALLOW_POST_TOPICS_CHECKBOX = 'pre_allow_post_topics_checkbox';

	/** Also for the guests, who have no such checkbox. */
	public const PRE_ALLOW_EDIT_POSTS_CHECKBOX = 'pre_allow_edit_posts_checkbox';

	/** Not for the guests, as the next two. */
	public const PRE_ALLOW_DELETE_POSTS_CHECKBOX = 'pre_allow_delete_posts_checkbox';

	public const PRE_ALLOW_DELETE_TOPICS_CHECKBOX = 'pre_allow_delete_topics_checkbox';

	public const PRE_ALLOW_SET_USER_TITLE_CHECKBOX = 'pre_allow_set_user_title_checkbox';

	public const PRE_ALLOW_SEARCH_CHECKBOX = 'pre_allow_search_checkbox';

	public const PRE_ALLOW_SEARCH_USERS_CHECKBOX = 'pre_allow_search_users_checkbox';

	/** Also for the guests, who have no such checkbox. */
	public const PRE_ALLOW_SEND_EMAIL_CHECKBOX = 'pre_allow_send_email_checkbox';

	public const PRE_USER_PERMISSIONS_FIELDSET_END = 'pre_user_permissions_fieldset_end';

	public const USER_PERMISSIONS_FIELDSET_END = 'user_permissions_fieldset_end';

	public const PRE_FLOOD_FIELDSET = 'pre_flood_fieldset';

	public const PRE_POST_INTERVAL = 'pre_post_interval';

	public const PRE_SEARCH_INTERVAL = 'pre_search_interval';

	/** After the search interval, also for the guests, who have no email interval. */
	public const PRE_EMAIL_INTERVAL = 'pre_email_interval';

	/** Right before the email interval: the point the page fired at PRE_EMAIL_INTERVAL, fired again. */
	public const PRE_EMAIL_INTERVAL_FIELD = 'pre_email_interval_field';

	public const PRE_FLOOD_FIELDSET_END = 'pre_flood_fieldset_end';

	public const FLOOD_FIELDSET_END = 'flood_fieldset_end';

	public const END = 'end';

	public const POSITIONS = array(
		self::OUTPUT_START, self::PRE_BASIC_DETAILS_FIELDSET, self::PRE_GROUP_TITLE, self::PRE_USER_TITLE, self::PRE_BASIC_DETAILS_FIELDSET_END, self::BASIC_DETAILS_FIELDSET_END,
		self::PRE_PERMISSIONS_FIELDSET, self::PRE_MOD_PERMISSIONS_FIELDSET, self::PRE_ALLOW_MODERATE_CHECKBOX, self::PRE_ALLOW_MOD_EDIT_PROFILES_CHECKBOX,
		self::PRE_ALLOW_MOD_EDIT_USERBANE_CHECKBOX, self::PRE_ALLOW_MOD_CHANGE_PASS_CHECKBOX, self::PRE_ALLOW_MOD_BAN_USERS_CHECKBOX, self::PRE_MOD_PERMISSIONS_FIELDSET_END,
		self::MOD_PERMISSIONS_FIELDSET_END, self::PRE_ALLOW_READ_BOARD_CHECKBOX, self::PRE_ALLOW_VIEW_USERS_CHECKBOX, self::PRE_ALLOW_POST_REPLIES_CHECKBOX,
		self::PRE_ALLOW_POST_TOPICS_CHECKBOX, self::PRE_ALLOW_EDIT_POSTS_CHECKBOX, self::PRE_ALLOW_DELETE_POSTS_CHECKBOX, self::PRE_ALLOW_DELETE_TOPICS_CHECKBOX,
		self::PRE_ALLOW_SET_USER_TITLE_CHECKBOX, self::PRE_ALLOW_SEARCH_CHECKBOX, self::PRE_ALLOW_SEARCH_USERS_CHECKBOX, self::PRE_ALLOW_SEND_EMAIL_CHECKBOX,
		self::PRE_USER_PERMISSIONS_FIELDSET_END, self::USER_PERMISSIONS_FIELDSET_END, self::PRE_FLOOD_FIELDSET, self::PRE_POST_INTERVAL, self::PRE_SEARCH_INTERVAL,
		self::PRE_EMAIL_INTERVAL, self::PRE_EMAIL_INTERVAL_FIELD, self::PRE_FLOOD_FIELDSET_END, self::FLOOD_FIELDSET_END, self::END,
	);

	/** @param bool $adding whether the form adds a group based on $group, rather than editing $group */
	public function __construct(private readonly string $position, private readonly GroupInterface $group, private readonly bool $adding, int $groupCount, int $itemCount, int $fieldCount) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The form adding or editing a group has no position "%s"', $position));

		$this->count($groupCount, $itemCount, $fieldCount);
	}

	public function position(): string {
		return $this->position;
	}

	/** The group edited, or the one a new group is based on. */
	public function group(): GroupInterface {
		return $this->group;
	}

	public function isAdding(): bool {
		return $this->adding;
	}
}
