<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Event;

use InvalidArgumentException;
use PunBB\Module\Forums\Api\Data\ForumInterface;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\FormMarkup;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * A position in the form editing a forum outside a group's permissions, which
 * an observer may add markup at. The form numbers its field groups, items and
 * fields in order, and the permissions part numbers its groups and items from
 * one again. Up to the permissions part the lines telling how permissions
 * work, named, may still change.
 */
final class ForumFormRendering implements EventInterface {
	use FormMarkup;
	use MarkupEntries;

	public const OUTPUT_START = 'output_start';

	public const PRE_DETAILS_FIELDSET = 'pre_details_fieldset';

	public const PRE_FORUM_NAME = 'pre_forum_name';

	public const PRE_FORUM_DESCRIP = 'pre_forum_descrip';

	public const PRE_FORUM_CAT = 'pre_forum_cat';

	public const PRE_FORUM_SORT_BY = 'pre_forum_sort_by';

	/** Inside the sorting's list, after its options. */
	public const MODIFY_SORT_BY = 'modify_sort_by';

	public const PRE_FORUM_REDIRECT_URL = 'pre_forum_redirect_url';

	public const PRE_DETAILS_FIELDSET_END = 'pre_details_fieldset_end';

	public const DETAILS_FIELDSET_END = 'details_fieldset_end';

	/** The last position the lines may change at, with the groups and items counted from one again. */
	public const PRE_PERMISSIONS_PART = 'pre_permissions_part';

	public const END = 'end';

	public const POSITIONS = array(
		self::OUTPUT_START, self::PRE_DETAILS_FIELDSET, self::PRE_FORUM_NAME, self::PRE_FORUM_DESCRIP, self::PRE_FORUM_CAT, self::PRE_FORUM_SORT_BY,
		self::MODIFY_SORT_BY, self::PRE_FORUM_REDIRECT_URL, self::PRE_DETAILS_FIELDSET_END, self::DETAILS_FIELDSET_END, self::PRE_PERMISSIONS_PART, self::END,
	);

	/** @param array<string, string> $lines the lines telling how permissions work, where they may still change */
	public function __construct(private readonly string $position, private readonly ForumInterface $forum, int $groupCount, int $itemCount, int $fieldCount, array $lines = array()) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The form editing a forum has no position "%s"', $position));

		$this->count($groupCount, $itemCount, $fieldCount);
		$this->entries = $lines;
	}

	public function position(): string {
		return $this->position;
	}

	public function forum(): ForumInterface {
		return $this->forum;
	}

	/** Whether the lines telling how permissions work may still change here. */
	public function carriesLines(): bool {
		return $this->position !== self::END;
	}

	private function accept(string $name): void {
		if (!$this->carriesLines())
			throw new InvalidArgumentException('The lines telling how permissions work are shown by now');
	}
}
