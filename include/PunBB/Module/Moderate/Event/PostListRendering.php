<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedTopicInterface;

/**
 * A position in the moderation of a topic's posts, which an observer may add
 * markup at and change what is still to be placed: at the start, the options
 * above the posts and below them; before the buttons, the buttons and the
 * options below; and at the end. Each group of parts is joined with spaces.
 */
final class PostListRendering implements EventInterface {
	use PartsByName;

	public const OUTPUT_START = 'output_start';

	/** After the posts, before the buttons. */
	public const PRE_MOD_OPTIONS = 'pre_mod_options';

	public const END = 'end';

	/** Selecting every post. */
	public const HEAD_OPTIONS = 'head_options';

	public const FOOT_OPTIONS = 'foot_options';

	/** Deleting or splitting off the posts selected, deleting the topic. */
	public const MOD_OPTIONS = 'mod_options';

	private string $markup = '';

	public function __construct(private readonly string $position, private readonly ModeratedForumInterface $forum, private readonly ModeratedTopicInterface $topic, Parts $headOptions, Parts $footOptions, Parts $modOptions) {
		if (!in_array($position, array(self::OUTPUT_START, self::PRE_MOD_OPTIONS, self::END), true))
			throw new InvalidArgumentException(sprintf('The moderation of a topic has no position "%s"', $position));

		$this->parts = array(self::HEAD_OPTIONS => $headOptions, self::FOOT_OPTIONS => $footOptions, self::MOD_OPTIONS => $modOptions);
	}

	public function position(): string {
		return $this->position;
	}

	public function forum(): ModeratedForumInterface {
		return $this->forum;
	}

	public function topic(): ModeratedTopicInterface {
		return $this->topic;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
