<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * A member asked for the first post of a topic they have not read, which is
 * about to be looked for among those posted since they last read the topic.
 */
final class NewPostSeeking implements EventInterface {
	/** @param int $lastViewed when the member last read the topic, or last visited when that is not known */
	public function __construct(private readonly int $topicId, private readonly int $lastViewed) {}

	public function topicId(): int {
		return $this->topicId;
	}

	public function lastViewed(): int {
		return $this->lastViewed;
	}
}
