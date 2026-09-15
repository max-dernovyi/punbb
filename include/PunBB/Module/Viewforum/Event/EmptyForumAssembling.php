<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;
use PunBB\Module\Viewforum\Api\Data\ViewedForumInterface;

/**
 * The row saying a forum has no topics, before it is placed: the lines of its
 * subject cell, the heading and the invitation to post, each markup. Markup
 * appended goes before the list.
 */
final class EmptyForumAssembling implements EventInterface {
	use MarkupEntries;

	private string $markup = '';

	/** @param array<string, string> $lines */
	public function __construct(private readonly ViewedForumInterface $forum, array $lines) {
		$this->entries = $lines;
	}

	public function forum(): ViewedForumInterface {
		return $this->forum;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}

	private function accept(string $name): void {}
}
