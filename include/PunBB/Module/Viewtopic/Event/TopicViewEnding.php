<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Viewtopic\Api\Data\ViewedTopicInterface;

/**
 * The end of a topic's posts, where an observer may add markup after them.
 */
final class TopicViewEnding implements EventInterface {
	private string $markup = '';

	public function __construct(private readonly ViewedTopicInterface $topic) {}

	public function topic(): ViewedTopicInterface {
		return $this->topic;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
