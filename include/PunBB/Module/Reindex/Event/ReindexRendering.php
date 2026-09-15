<?php

declare(strict_types=1);

namespace PunBB\Module\Reindex\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in the form starting a rebuild, which an observer may add markup
 * at. The form numbers its field groups, items and fields in order; markup
 * that adds any of them counts them, and the form numbers on from there.
 */
final class ReindexRendering implements EventInterface {
	public const MAIN_OUTPUT_START = 'main_output_start';

	public const PRE_REBUILD_FIELDSET = 'pre_rebuild_fieldset';

	public const PRE_REBUILD_PER_PAGE = 'pre_rebuild_per_page';

	public const PRE_REBUILD_START_POST = 'pre_rebuild_start_post';

	public const PRE_REBUILD_EMPTY_INDEX = 'pre_rebuild_empty_index';

	public const PRE_REBUILD_FIELDSET_END = 'pre_rebuild_fieldset_end';

	public const REBUILD_FIELDSET_END = 'rebuild_fieldset_end';

	public const END = 'end';

	public const POSITIONS = array(
		self::MAIN_OUTPUT_START, self::PRE_REBUILD_FIELDSET, self::PRE_REBUILD_PER_PAGE, self::PRE_REBUILD_START_POST,
		self::PRE_REBUILD_EMPTY_INDEX, self::PRE_REBUILD_FIELDSET_END, self::REBUILD_FIELDSET_END, self::END,
	);

	private string $markup = '';

	public function __construct(private readonly string $position, private int $groupCount, private int $itemCount, private int $fieldCount) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The rebuild form has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function groupCount(): int {
		return $this->groupCount;
	}

	public function itemCount(): int {
		return $this->itemCount;
	}

	public function fieldCount(): int {
		return $this->fieldCount;
	}

	/** The form's counts once the markup added here is counted in. */
	public function count(int $groups, int $items, int $fields): void {
		$this->groupCount = $groups;
		$this->itemCount = $items;
		$this->fieldCount = $fields;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
