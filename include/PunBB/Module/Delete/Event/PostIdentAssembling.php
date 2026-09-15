<?php

declare(strict_types=1);

namespace PunBB\Module\Delete\Event;

use PunBB\Module\Delete\Api\Data\DeletablePostInterface;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * The heading of the post the deletion page shows, its parts named, before
 * they are joined: the byline and the permalink. Each part is markup.
 */
final class PostIdentAssembling implements EventInterface {
	use MarkupEntries;

	/** @param array<string, string> $parts */
	public function __construct(private readonly DeletablePostInterface $post, array $parts) {
		$this->entries = $parts;
	}

	public function post(): DeletablePostInterface {
		return $this->post;
	}

	private function accept(string $name): void {}
}
