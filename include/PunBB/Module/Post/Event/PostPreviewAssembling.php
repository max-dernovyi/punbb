<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;
use PunBB\Module\Post\Api\Data\LocationInterface;

/**
 * The preview of a post, before it is placed: the parts of its heading, each
 * markup — num, byline, link — and the message as it would be shown. Markup
 * appended goes before the preview.
 */
final class PostPreviewAssembling implements EventInterface {
	use MarkupEntries;

	private string $markup = '';

	/** @param array<string, string> $ident */
	public function __construct(private readonly LocationInterface $location, array $ident, private string $message) {
		$this->entries = $ident;
	}

	public function location(): LocationInterface {
		return $this->location;
	}

	/** The message, parsed: markup. */
	public function message(): string {
		return $this->message;
	}

	public function setMessage(string $markup): void {
		$this->message = $markup;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}

	private function accept(string $name): void {}
}
