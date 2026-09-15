<?php

declare(strict_types=1);

namespace PunBB\Module\Delete\Event;

use InvalidArgumentException;
use PunBB\Module\Delete\Api\Data\DeletablePostInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in the deletion page, which an observer may add markup at. The
 * form numbers its field groups, items and fields in order; markup that adds
 * any of them counts them, and the form numbers on from there.
 */
final class DeletionRendering implements EventInterface {
	public const MAIN_OUTPUT_START = 'main_output_start';

	public const PRE_POST_DISPLAY = 'pre_post_display';

	/** Inside the post's heading, after the byline. */
	public const NEW_POST_HEAD_OPTION = 'new_post_head_option';

	/** Inside the post, after its text. */
	public const NEW_POST_ENTRY_DATA = 'new_post_entry_data';

	public const PRE_CONFIRM_DELETE_FIELDSET = 'pre_confirm_delete_fieldset';

	public const PRE_CONFIRM_DELETE_CHECKBOX = 'pre_confirm_delete_checkbox';

	public const PRE_CONFIRM_DELETE_FIELDSET_END = 'pre_confirm_delete_fieldset_end';

	public const CONFIRM_DELETE_FIELDSET_END = 'confirm_delete_fieldset_end';

	public const END = 'end';

	public const POSITIONS = array(
		self::MAIN_OUTPUT_START, self::PRE_POST_DISPLAY, self::NEW_POST_HEAD_OPTION, self::NEW_POST_ENTRY_DATA, self::PRE_CONFIRM_DELETE_FIELDSET,
		self::PRE_CONFIRM_DELETE_CHECKBOX, self::PRE_CONFIRM_DELETE_FIELDSET_END, self::CONFIRM_DELETE_FIELDSET_END, self::END,
	);

	private string $markup = '';

	public function __construct(
		private readonly string $position,
		private readonly DeletablePostInterface $post,
		private int $groupCount,
		private int $itemCount,
		private int $fieldCount
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The deletion page has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function post(): DeletablePostInterface {
		return $this->post;
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
