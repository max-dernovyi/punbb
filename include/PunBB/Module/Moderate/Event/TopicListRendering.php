<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;

/**
 * A position in the moderation of a forum's topics, which an observer may add
 * markup at and change what is still to be placed: at the start, the labels
 * the summary names the subject column with, joined with spaces, and those it
 * names the figures with, joined with commas, and the options above and below
 * the list; after the list; before the buttons, the buttons and the options
 * below; and at the end.
 */
final class TopicListRendering implements EventInterface {
	use PartsByName;

	public const OUTPUT_START = 'output_start';

	/** After the list, before the buttons. */
	public const POST_TOPIC_LIST = 'post_topic_list';

	public const PRE_MOD_OPTIONS = 'pre_mod_options';

	public const END = 'end';

	public const SUBJECT = 'subject';

	public const INFO = 'info';

	/** Selecting every topic. */
	public const HEAD_OPTIONS = 'head_options';

	public const FOOT_OPTIONS = 'foot_options';

	/** Moving, deleting, merging, opening or closing the topics selected. */
	public const MOD_OPTIONS = 'mod_options';

	private string $markup = '';

	public function __construct(private readonly string $position, private readonly ModeratedForumInterface $forum, Parts $subject, Parts $info, Parts $headOptions, Parts $footOptions, Parts $modOptions) {
		if (!in_array($position, array(self::OUTPUT_START, self::POST_TOPIC_LIST, self::PRE_MOD_OPTIONS, self::END), true))
			throw new InvalidArgumentException(sprintf('The moderation of a forum has no position "%s"', $position));

		$this->parts = array(self::SUBJECT => $subject, self::INFO => $info, self::HEAD_OPTIONS => $headOptions, self::FOOT_OPTIONS => $footOptions, self::MOD_OPTIONS => $modOptions);
	}

	public function position(): string {
		return $this->position;
	}

	public function forum(): ModeratedForumInterface {
		return $this->forum;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
