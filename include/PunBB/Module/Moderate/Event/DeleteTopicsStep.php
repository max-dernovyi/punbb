<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of deleting topics: once confirmed, before they are checked; and once
 * gone, with the forums synced and the posts they held, before the browser is
 * sent back to the forum.
 */
final class DeleteTopicsStep implements EventInterface {
	public const CONFIRMED = 'confirmed';

	public const DELETED = 'deleted';

	/**
	 * @param list<int> $topicIds
	 * @param list<int> $forumIds the forums synced once they are gone, this one first
	 * @param list<int> $postIds the posts they held
	 */
	public function __construct(private readonly string $step, private readonly array $topicIds, private readonly array $forumIds = array(), private readonly array $postIds = array()) {
		if (!in_array($step, array(self::CONFIRMED, self::DELETED), true))
			throw new InvalidArgumentException(sprintf('Deleting topics has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** @return list<int> */
	public function topicIds(): array {
		return $this->topicIds;
	}

	/** @return list<int> */
	public function forumIds(): array {
		return $this->forumIds;
	}

	/** @return list<int> */
	public function postIds(): array {
		return $this->postIds;
	}
}
