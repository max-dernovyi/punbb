<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Viewtopic\Api\Data\ViewedTopicInterface;

/**
 * The options above and below a topic's posts, before anything of the posts is
 * placed: the links above — its feed, subscribing — and those below, which
 * only its moderators get, each joined with spaces. Markup appended goes
 * before the posts.
 */
final class TopicOptionsAssembling implements EventInterface {
	use PartsByName;

	public const HEAD_OPTIONS = 'head_options';

	/** Moving, deleting, closing and sticking the topic, and moderating its posts. */
	public const FOOT_OPTIONS = 'foot_options';

	private string $markup = '';

	/** @param bool $moderating whether the visitor moderates the topic, and gets the options below it */
	public function __construct(private readonly ViewedTopicInterface $topic, private readonly bool $moderating, Parts $headOptions, Parts $footOptions) {
		$this->parts = array(self::HEAD_OPTIONS => $headOptions, self::FOOT_OPTIONS => $footOptions);
	}

	public function topic(): ViewedTopicInterface {
		return $this->topic;
	}

	public function moderating(): bool {
		return $this->moderating;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
