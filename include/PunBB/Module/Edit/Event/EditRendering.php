<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Event;

use InvalidArgumentException;
use PunBB\Module\Edit\Api\Data\EditablePostInterface;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;

/**
 * A position in the edit page, which an observer may add markup at. The form
 * numbers its field groups, items and fields in order; markup that adds any
 * of them counts them, and the form numbers on from there. At the start the
 * form's hidden fields, its attributes, the links to the help on what a
 * message may use and the errors, each named, may still change.
 */
final class EditRendering implements EventInterface {
	use PartsByName;

	public const MAIN_OUTPUT_START = 'main_output_start';

	/** Inside the preview's heading. */
	public const PREVIEW_NEW_POST_HEAD_OPTION = 'preview_new_post_head_option';

	/** Inside the preview, after its message. */
	public const PREVIEW_NEW_POST_ENTRY_DATA = 'preview_new_post_entry_data';

	public const PRE_MAIN_FIELDSET = 'pre_main_fieldset';

	public const PRE_SUBJECT = 'pre_subject';

	/** Before the message's field, on the line the subject's ends. */
	public const PRE_MESSAGE_BOX = 'pre_message_box';

	/** Inside the checkboxes' fieldset, after them. */
	public const PRE_CHECKBOX_FIELDSET_END = 'pre_checkbox_fieldset_end';

	public const PRE_MAIN_FIELDSET_END = 'pre_main_fieldset_end';

	public const MAIN_FIELDSET_END = 'main_fieldset_end';

	public const END = 'end';

	public const POSITIONS = array(
		self::MAIN_OUTPUT_START, self::PREVIEW_NEW_POST_HEAD_OPTION, self::PREVIEW_NEW_POST_ENTRY_DATA, self::PRE_MAIN_FIELDSET, self::PRE_SUBJECT,
		self::PRE_MESSAGE_BOX, self::PRE_CHECKBOX_FIELDSET_END, self::PRE_MAIN_FIELDSET_END, self::MAIN_FIELDSET_END, self::END,
	);

	/** The groups of parts the start carries. */
	public const HIDDEN_FIELDS = 'hidden_fields';

	public const FORM_ATTRIBUTES = 'form_attributes';

	public const TEXT_OPTIONS = 'text_options';

	public const ERRORS = 'errors';

	private string $markup = '';

	/** @param string $action the URL the form posts to */
	public function __construct(
		private readonly string $position,
		private readonly EditablePostInterface $post,
		private readonly string $action,
		private int $groupCount,
		private int $itemCount,
		private int $fieldCount,
		?Parts $hiddenFields = null,
		?Parts $formAttributes = null,
		?Parts $textOptions = null,
		?Parts $errors = null
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The edit page has no position "%s"', $position));

		$this->parts = array(
			self::HIDDEN_FIELDS		=> $hiddenFields ?? new Parts(),
			self::FORM_ATTRIBUTES	=> $formAttributes ?? new Parts(),
			self::TEXT_OPTIONS		=> $textOptions ?? new Parts(),
			self::ERRORS			=> $errors ?? new Parts(),
		);
	}

	public function position(): string {
		return $this->position;
	}

	public function post(): EditablePostInterface {
		return $this->post;
	}

	public function action(): string {
		return $this->action;
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
