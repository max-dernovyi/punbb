<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Event;

use InvalidArgumentException;
use PunBB\Module\Forums\Api\Data\ForumPermissionsInterface;
use PunBB\Module\Forums\Api\Data\GroupPermissionsInterface;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\FormMarkup;

/**
 * A position in a group's fieldset of the forum's permissions, which an
 * observer may add markup at, with what the fieldset's checkboxes show. Markup
 * that adds a group, an item or a field counts it, and the form numbers on
 * from there.
 */
final class GroupPermissionRendering implements EventInterface {
	use FormMarkup;

	public const PRE_CUR_GROUP_PERMISSIONS_FIELDSET = 'pre_cur_group_permissions_fieldset';

	public const PRE_CUR_GROUP_READ_FORUM_PERMISSION = 'pre_cur_group_read_forum_permission';

	public const PRE_CUR_GROUP_POST_REPLIES_PERMISSION = 'pre_cur_group_post_replies_permission';

	public const PRE_CUR_GROUP_POST_TOPICS_PERMISSION = 'pre_cur_group_post_topics_permission';

	public const POST_CUR_GROUP_POST_TOPICS_PERMISSION = 'post_cur_group_post_topics_permission';

	public const PRE_CUR_GROUP_PERMISSIONS_FIELDSET_END = 'pre_cur_group_permissions_fieldset_end';

	/** After the fieldset. */
	public const CUR_GROUP_PERMISSIONS_FIELDSET_END = 'cur_group_permissions_fieldset_end';

	public const POSITIONS = array(
		self::PRE_CUR_GROUP_PERMISSIONS_FIELDSET, self::PRE_CUR_GROUP_READ_FORUM_PERMISSION, self::PRE_CUR_GROUP_POST_REPLIES_PERMISSION, self::PRE_CUR_GROUP_POST_TOPICS_PERMISSION,
		self::POST_CUR_GROUP_POST_TOPICS_PERMISSION, self::PRE_CUR_GROUP_PERMISSIONS_FIELDSET_END, self::CUR_GROUP_PERMISSIONS_FIELDSET_END,
	);

	public function __construct(
		private readonly string $position,
		private readonly GroupPermissionsInterface $group,
		private readonly ForumPermissionsInterface $shown,
		int $groupCount,
		int $itemCount,
		int $fieldCount
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('A group\'s permissions in a forum have no position "%s"', $position));

		$this->count($groupCount, $itemCount, $fieldCount);
	}

	public function position(): string {
		return $this->position;
	}

	public function group(): GroupPermissionsInterface {
		return $this->group;
	}

	/** What the fieldset's checkboxes show. */
	public function shown(): ForumPermissionsInterface {
		return $this->shown;
	}
}
