<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Viewforum\Api\Data\ViewedForumInterface;

/**
 * A step of showing a forum: the forum found, the visitor about to be sent on
 * to a forum on another site, and the page of topics to show worked out.
 */
final class ForumViewStep implements EventInterface {
	/** The forum was found and the visitor may read it. */
	public const SELECTED = 'selected';

	/** The forum is on another site, and the visitor is sent there next. */
	public const REDIRECTING = 'redirecting';

	/** Which page of topics to show is worked out, before they are read. */
	public const PAGINATED = 'paginated';

	private const STEPS = array(self::SELECTED, self::REDIRECTING, self::PAGINATED);

	/**
	 * @param bool $moderating whether the visitor administers the board or moderates the forum; known once paginated
	 * @param bool $mayPost whether the visitor may start a topic in the forum; known once paginated
	 * @param int $page the page of topics shown; 0 before it is worked out
	 */
	public function __construct(
		private readonly string $step,
		private readonly ViewedForumInterface $forum,
		private readonly bool $moderating = false,
		private readonly bool $mayPost = false,
		private readonly int $page = 0,
		private readonly int $pageCount = 0
	) {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Showing a forum has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function forum(): ViewedForumInterface {
		return $this->forum;
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
}
