<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Event;

use PunBB\Module\Edit\Api\Data\EditablePostInterface;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * The preview of an edit, before it is placed: the parts of its heading, each
 * markup — num, byline, link — and the message as it would be shown. Markup
 * appended goes before the preview.
 */
final class EditPreviewAssembling implements EventInterface {
	use MarkupEntries;

	private string $markup = '';

	/** @param array<string, string> $ident */
	public function __construct(private readonly EditablePostInterface $post, array $ident, private string $message) {
		$this->entries = $ident;
	}

	public function post(): EditablePostInterface {
		return $this->post;
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
