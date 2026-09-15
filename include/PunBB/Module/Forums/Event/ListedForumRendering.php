<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Event;

use InvalidArgumentException;
use PunBB\Module\Forums\Api\Data\ListedForumInterface;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\FormMarkup;

/**
 * A position in a listed forum's fieldset of the form updating the positions,
 * which an observer may add markup at. Each category numbers its fieldsets
 * from one; markup that adds a group, an item or a field counts it.
 */
final class ListedForumRendering implements EventInterface {
	use FormMarkup;

	public const PRE_EDIT_CUR_FORUM_FIELDSET = 'pre_edit_cur_forum_fieldset';

	public const PRE_EDIT_CUR_FORUM_NAME = 'pre_edit_cur_forum_name';

	public const PRE_EDIT_CUR_FORUM_POSITION = 'pre_edit_cur_forum_position';

	public const PRE_EDIT_CUR_FORUM_FIELDSET_END = 'pre_edit_cur_forum_fieldset_end';

	/** After the fieldset. */
	public const EDIT_CUR_FORUM_FIELDSET_END = 'edit_cur_forum_fieldset_end';

	public const POSITIONS = array(
		self::PRE_EDIT_CUR_FORUM_FIELDSET, self::PRE_EDIT_CUR_FORUM_NAME, self::PRE_EDIT_CUR_FORUM_POSITION, self::PRE_EDIT_CUR_FORUM_FIELDSET_END, self::EDIT_CUR_FORUM_FIELDSET_END,
	);

	public function __construct(private readonly string $position, private readonly ListedForumInterface $forum, int $groupCount, int $itemCount, int $fieldCount) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('A listed forum\'s fieldset has no position "%s"', $position));

		$this->count($groupCount, $itemCount, $fieldCount);
	}

	public function position(): string {
		return $this->position;
	}

	public function forum(): ListedForumInterface {
		return $this->forum;
	}
}
