<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Post\Api\Data\LocationInterface;

/**
 * The post a reply quotes was found, before it is put into the message field:
 * an observer may change who wrote it and what it says, both text.
 */
final class QuoteSelected implements EventInterface {
	public function __construct(private readonly LocationInterface $location, private readonly int $postId, private string $poster, private string $message) {}

	public function location(): LocationInterface {
		return $this->location;
	}

	/** The post quoted. */
	public function postId(): int {
		return $this->postId;
	}

	public function poster(): string {
		return $this->poster;
	}

	public function message(): string {
		return $this->message;
	}

	public function change(string $poster, string $message): void {
		$this->poster = $poster;
		$this->message = $message;
	}
}
