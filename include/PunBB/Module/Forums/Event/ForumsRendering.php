<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\FormMarkup;

/**
 * A position in the forums page outside a listed forum's fieldset, which an
 * observer may add markup at: around the form adding a forum, and at the end
 * of the page. The forms number their field groups, items and fields in order.
 */
final class ForumsRendering implements EventInterface {
	use FormMarkup;

	public const MAIN_OUTPUT_START = 'main_output_start';

	public const PRE_ADD_FORUM_FIELDSET = 'pre_add_forum_fieldset';

	public const PRE_NEW_FORUM_NAME = 'pre_new_forum_name';

	public const PRE_NEW_FORUM_POSITION = 'pre_new_forum_position';

	public const PRE_NEW_FORUM_CAT = 'pre_new_forum_cat';

	public const PRE_ADD_FORUM_FIELDSET_END = 'pre_add_forum_fieldset_end';

	/** After the fieldset, before the form's button. */
	public const ADD_FORUM_FIELDSET_END = 'add_forum_fieldset_end';

	/** After the form listing the forums, which is there only when a forum is. */
	public const END = 'end';

	public const POSITIONS = array(
		self::MAIN_OUTPUT_START, self::PRE_ADD_FORUM_FIELDSET, self::PRE_NEW_FORUM_NAME, self::PRE_NEW_FORUM_POSITION, self::PRE_NEW_FORUM_CAT,
		self::PRE_ADD_FORUM_FIELDSET_END, self::ADD_FORUM_FIELDSET_END, self::END,
	);

	public function __construct(private readonly string $position, int $groupCount, int $itemCount, int $fieldCount) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The forums page has no position "%s"', $position));

		$this->count($groupCount, $itemCount, $fieldCount);
	}

	public function position(): string {
		return $this->position;
	}
}
