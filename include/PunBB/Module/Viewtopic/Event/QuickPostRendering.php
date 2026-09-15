<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Viewtopic\Api\Data\ViewedTopicInterface;

/**
 * A position in the quick reply form below a topic, which an observer may add
 * markup at. Before it is displayed, its hidden fields, its attributes and
 * the links to the help on what a message may use, each named, may still change.
 */
final class QuickPostRendering implements EventInterface {
	use PartsByName;

	/** Before anything of the form is built. */
	public const OUTPUT_START = 'output_start';

	public const PRE_DISPLAY = 'pre_display';

	public const PRE_FIELDSET = 'pre_fieldset';

	public const PRE_MESSAGE_BOX = 'pre_message_box';

	public const PRE_FIELDSET_END = 'pre_fieldset_end';

	public const FIELDSET_END = 'fieldset_end';

	public const END = 'end';

	public const POSITIONS = array(self::OUTPUT_START, self::PRE_DISPLAY, self::PRE_FIELDSET, self::PRE_MESSAGE_BOX, self::PRE_FIELDSET_END, self::FIELDSET_END, self::END);

	public const HIDDEN_FIELDS = 'hidden_fields';

	public const FORM_ATTRIBUTES = 'form_attributes';

	public const TEXT_OPTIONS = 'text_options';

	private string $markup = '';

	/** @param string $action the URL the form posts to */
	public function __construct(
		private readonly string $position,
		private readonly ViewedTopicInterface $topic,
		private readonly string $action,
		?Parts $hiddenFields = null,
		?Parts $formAttributes = null,
		?Parts $textOptions = null
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The quick reply form has no position "%s"', $position));

		$this->parts = array(
			self::HIDDEN_FIELDS		=> $hiddenFields ?? new Parts(),
			self::FORM_ATTRIBUTES	=> $formAttributes ?? new Parts(),
			self::TEXT_OPTIONS		=> $textOptions ?? new Parts(),
		);
	}

	public function position(): string {
		return $this->position;
	}

	public function topic(): ViewedTopicInterface {
		return $this->topic;
	}

	public function action(): string {
		return $this->action;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
