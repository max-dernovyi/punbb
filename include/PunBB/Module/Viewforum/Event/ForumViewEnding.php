<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Viewforum\Api\Data\ViewedForumInterface;

/**
 * The end of a forum's topic list, where an observer may add markup after it.
 */
final class ForumViewEnding implements EventInterface {
	private string $markup = '';

	public function __construct(private readonly ViewedForumInterface $forum) {}

	public function forum(): ViewedForumInterface {
		return $this->forum;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
