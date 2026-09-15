<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Post\Api\Data\LocationInterface;

/**
 * A position in the posting page, which an observer may add markup at. The
 * form numbers its field groups, items and fields in order; markup that adds
 * any of them counts them, and the form numbers on from there. At the start
 * the form's hidden fields, its attributes and the links to the help on what
 * a message may use, and before the errors the errors, each named, may still
 * change.
 */
final class PostRendering implements EventInterface {
	use PartsByName;

	public const MAIN_OUTPUT_START = 'main_output_start';

	/** Inside the preview's heading. */
	public const PREVIEW_NEW_POST_HEAD_OPTION = 'preview_new_post_head_option';

	/** Inside the preview, after its message. */
	public const PREVIEW_NEW_POST_ENTRY_DATA = 'preview_new_post_entry_data';

	/** Only when something stops the post. */
	public const PRE_POST_ERRORS = 'pre_post_errors';

	/** The guest's fieldset: only for a guest, which the form numbers its groups and items afresh after. */
	public const PRE_GUEST_INFO_FIELDSET = 'pre_guest_info_fieldset';

	public const PRE_GUEST_USERNAME = 'pre_guest_username';

	public const PRE_GUEST_EMAIL = 'pre_guest_email';

	public const PRE_GUEST_INFO_FIELDSET_END = 'pre_guest_info_fieldset_end';

	public const GUEST_INFO_FIELDSET_END = 'guest_info_fieldset_end';

	public const PRE_REQ_INFO_FIELDSET = 'pre_req_info_fieldset';

	/** Only for a new topic. */
	public const PRE_REQ_SUBJECT = 'pre_req_subject';

	public const PRE_POST_CONTENTS = 'pre_post_contents';

	/** Inside the checkboxes' fieldset, after them. */
	public const PRE_CHECKBOX_FIELDSET_END = 'pre_checkbox_fieldset_end';

	public const PRE_REQ_INFO_FIELDSET_END = 'pre_req_info_fieldset_end';

	public const REQ_INFO_FIELDSET_END = 'req_info_fieldset_end';

	/** After the form, before the topic review. */
	public const MAIN_OUTPUT_END = 'main_output_end';

	public const END = 'end';

	public const POSITIONS = array(
		self::MAIN_OUTPUT_START, self::PREVIEW_NEW_POST_HEAD_OPTION, self::PREVIEW_NEW_POST_ENTRY_DATA, self::PRE_POST_ERRORS,
		self::PRE_GUEST_INFO_FIELDSET, self::PRE_GUEST_USERNAME, self::PRE_GUEST_EMAIL, self::PRE_GUEST_INFO_FIELDSET_END, self::GUEST_INFO_FIELDSET_END,
		self::PRE_REQ_INFO_FIELDSET, self::PRE_REQ_SUBJECT, self::PRE_POST_CONTENTS, self::PRE_CHECKBOX_FIELDSET_END,
		self::PRE_REQ_INFO_FIELDSET_END, self::REQ_INFO_FIELDSET_END, self::MAIN_OUTPUT_END, self::END,
	);

	/** The groups of parts the event carries. */
	public const HIDDEN_FIELDS = 'hidden_fields';

	public const FORM_ATTRIBUTES = 'form_attributes';

	public const TEXT_OPTIONS = 'text_options';

	public const ERRORS = 'errors';

	private string $markup = '';

	/** @param string $action the URL the form posts to */
	public function __construct(
		private readonly string $position,
		private readonly LocationInterface $location,
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
			throw new InvalidArgumentException(sprintf('The posting page has no position "%s"', $position));

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

	public function location(): LocationInterface {
		return $this->location;
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
