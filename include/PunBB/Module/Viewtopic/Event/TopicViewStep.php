<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Viewtopic\Api\Data\ViewedTopicInterface;

/**
 * A step of showing a topic: the topic found and the visitor allowed to read
 * it, and the page of posts to show worked out.
 */
final class TopicViewStep implements EventInterface {
	public const SELECTED = 'selected';

	/** Which page of posts to show is worked out, before anything of the page is built. */
	public const PAGINATED = 'paginated';

	private const STEPS = array(self::SELECTED, self::PAGINATED);

	/**
	 * @param bool $moderating whether the visitor administers the board or moderates the topic's forum; known once paginated
	 * @param bool $mayPost whether the visitor may reply; known once paginated
	 * @param int $page the page of posts shown; 0 before it is worked out
	 * @param int $offset how many posts come before the page
	 * @param int $last the number of the page's last post
	 * @param string $subject the subject as the page shows it, censored where the board censors; known once paginated
	 */
	public function __construct(
		private readonly string $step,
		private readonly ViewedTopicInterface $topic,
		private readonly bool $moderating = false,
		private readonly bool $mayPost = false,
		private readonly int $page = 0,
		private readonly int $pageCount = 0,
		private readonly int $offset = 0,
		private readonly int $last = 0,
		private readonly string $subject = ''
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Showing a topic has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function topic(): ViewedTopicInterface {
		return $this->topic;
	}

	public function moderating(): bool {
		return $this->moderating;
	}

	public function mayPost(): bool {
		return $this->mayPost;
	}

	public function page(): int {
		return $this->page;
	}

	public function pageCount(): int {
		return $this->pageCount;
	}

	public function offset(): int {
		return $this->offset;
	}

	public function last(): int {
		return $this->last;
	}

	public function shownSubject(): string {
		return $this->subject;
	}
}
