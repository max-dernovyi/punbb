<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A feed was asked for: the format it is written in and how many items it
 * carries, which an observer may change before anything is read.
 */
final class FeedRequested implements EventInterface {
	/** The formats a feed is written in. */
	public const TYPES = array('html', 'rss', 'atom', 'xml');

	public function __construct(private string $type, private int $count) {
		$this->setType($type);
	}

	/** 'html', 'rss', 'atom' or 'xml'. */
	public function type(): string {
		return $this->type;
	}

	public function setType(string $type): void {
		if (!in_array($type, self::TYPES, true))
			throw new InvalidArgumentException(sprintf('A feed has no format "%s"', $type));

		$this->type = $type;
	}

	/** How many items the feed carries. */
	public function count(): int {
		return $this->count;
	}

	public function setCount(int $count): void {
		$this->count = $count;
	}
}
