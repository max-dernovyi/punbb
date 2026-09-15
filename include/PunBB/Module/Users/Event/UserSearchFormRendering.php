<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\FormMarkup;

/**
 * A position in the search forms of the users page, which an observer may add
 * markup at: the form finding users by their details, contacts and activity,
 * with the order of its results, and the form finding them by an address. Each
 * of the first form's fieldsets numbers its items from one; its results and
 * the address form number their field groups and items from one again, their
 * fields on from the form before.
 */
final class UserSearchFormRendering implements EventInterface {
	use FormMarkup;

	public const OUTPUT_START = 'output_start';

	public const PRE_USER_DETAILS_FIELDSET = 'pre_user_details_fieldset';

	public const PRE_USERNAME = 'pre_username';

	public const PRE_USER_TITLE = 'pre_user_title';

	public const PRE_REALNAME = 'pre_realname';

	public const PRE_LOCATION = 'pre_location';

	public const PRE_SIGNATURE = 'pre_signature';

	public const PRE_ADMIN_NOTE = 'pre_admin_note';

	public const PRE_USER_DETAILS_FIELDSET_END = 'pre_user_details_fieldset_end';

	public const USER_DETAILS_FIELDSET_END = 'user_details_fieldset_end';

	public const PRE_USER_CONTACTS_FIELDSET = 'pre_user_contacts_fieldset';

	public const PRE_EMAIL = 'pre_email';

	public const PRE_WEBSITE = 'pre_website';

	public const PRE_JABBER = 'pre_jabber';

	public const PRE_ICQ = 'pre_icq';

	public const PRE_MSN = 'pre_msn';

	public const PRE_AIM = 'pre_aim';

	public const PRE_YAHOO = 'pre_yahoo';

	public const PRE_USER_CONTACTS_FIELDSET_END = 'pre_user_contacts_fieldset_end';

	public const USER_CONTACTS_FIELDSET_END = 'user_contacts_fieldset_end';

	public const PRE_USER_ACTIVITY_FIELDSET = 'pre_user_activity_fieldset';

	public const PRE_MIN_POSTS = 'pre_min_posts';

	public const PRE_MAX_POSTS = 'pre_max_posts';

	public const PRE_LAST_POST_AFTER = 'pre_last_post_after';

	public const PRE_LAST_POST_BEFORE = 'pre_last_post_before';

	public const PRE_REGISTERED_AFTER = 'pre_registered_after';

	public const PRE_REGISTERED_BEFORE = 'pre_registered_before';

	public const PRE_USER_ACTIVITY_FIELDSET_END = 'pre_user_activity_fieldset_end';

	public const USER_ACTIVITY_FIELDSET_END = 'user_activity_fieldset_end';

	public const PRE_RESULTS_FIELDSET = 'pre_results_fieldset';

	public const PRE_SORT_BY = 'pre_sort_by';

	/** After the orders the results can take, inside their list. */
	public const NEW_SORT_BY_OPTION = 'new_sort_by_option';

	public const PRE_SORT_ORDER = 'pre_sort_order';

	public const PRE_FILTER_GROUP = 'pre_filter_group';

	/** After the groups the search can be limited to, inside their list. */
	public const NEW_FILTER_GROUP_OPTION = 'new_filter_group_option';

	public const PRE_RESULTS_FIELDSET_END = 'pre_results_fieldset_end';

	/** After the results' fieldset, before the form's button. */
	public const RESULTS_FIELDSET_END = 'results_fieldset_end';

	public const PRE_IP_SEARCH_FIELDSET = 'pre_ip_search_fieldset';

	public const PRE_IP_ADDRESS = 'pre_ip_address';

	public const PRE_IP_SEARCH_FIELDSET_END = 'pre_ip_search_fieldset_end';

	public const IP_SEARCH_FIELDSET_END = 'ip_search_fieldset_end';

	/** After both forms. */
	public const END = 'end';

	public const POSITIONS = array(
		self::OUTPUT_START, self::PRE_USER_DETAILS_FIELDSET, self::PRE_USERNAME, self::PRE_USER_TITLE, self::PRE_REALNAME, self::PRE_LOCATION, self::PRE_SIGNATURE,
		self::PRE_ADMIN_NOTE, self::PRE_USER_DETAILS_FIELDSET_END, self::USER_DETAILS_FIELDSET_END, self::PRE_USER_CONTACTS_FIELDSET, self::PRE_EMAIL, self::PRE_WEBSITE,
		self::PRE_JABBER, self::PRE_ICQ, self::PRE_MSN, self::PRE_AIM, self::PRE_YAHOO, self::PRE_USER_CONTACTS_FIELDSET_END, self::USER_CONTACTS_FIELDSET_END,
		self::PRE_USER_ACTIVITY_FIELDSET, self::PRE_MIN_POSTS, self::PRE_MAX_POSTS, self::PRE_LAST_POST_AFTER, self::PRE_LAST_POST_BEFORE, self::PRE_REGISTERED_AFTER,
		self::PRE_REGISTERED_BEFORE, self::PRE_USER_ACTIVITY_FIELDSET_END, self::USER_ACTIVITY_FIELDSET_END, self::PRE_RESULTS_FIELDSET, self::PRE_SORT_BY,
		self::NEW_SORT_BY_OPTION, self::PRE_SORT_ORDER, self::PRE_FILTER_GROUP, self::NEW_FILTER_GROUP_OPTION, self::PRE_RESULTS_FIELDSET_END, self::RESULTS_FIELDSET_END,
		self::PRE_IP_SEARCH_FIELDSET, self::PRE_IP_ADDRESS, self::PRE_IP_SEARCH_FIELDSET_END, self::IP_SEARCH_FIELDSET_END, self::END,
	);

	public function __construct(private readonly string $position, int $groupCount, int $itemCount, int $fieldCount) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The users\' search forms have no position "%s"', $position));

		$this->count($groupCount, $itemCount, $fieldCount);
	}

	public function position(): string {
		return $this->position;
	}
}
