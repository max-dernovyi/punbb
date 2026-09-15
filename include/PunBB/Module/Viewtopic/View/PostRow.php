<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\View;

use PunBB\Module\Layout\View\Parts;

/**
 * A post in its topic's page while it is built: the parts of its heading, of
 * what identifies and describes its poster, of the poster's contacts, of its
 * actions and of the options below it, of its message, its classes, and the
 * heading of its message.
 */
final class PostRow {
	public readonly Parts $postIdent;

	public readonly Parts $authorIdent;

	public readonly Parts $authorInfo;

	public readonly Parts $postContacts;

	public readonly Parts $postActions;

	public readonly Parts $postOptions;

	public readonly Parts $message;

	public readonly Parts $status;

	private string $subject = '';

	public function __construct(?Parts $authorIdent = null, ?Parts $authorInfo = null, ?Parts $postContacts = null) {
		$this->postIdent = new Parts();
		$this->authorIdent = $authorIdent ?? new Parts();
		$this->authorInfo = $authorInfo ?? new Parts();
		$this->postContacts = $postContacts ?? new Parts();
		$this->postActions = new Parts();
		$this->postOptions = new Parts();
		$this->message = new Parts();
		$this->status = new Parts();
	}

	/** A copy of $parts, which changes to either leave the other as it is. */
	public static function copy(Parts $parts): Parts {
		$entries = array();
		foreach ($parts->names() as $name)
			$entries[$name] = (string) $parts->entry($name);

		return new Parts($entries);
	}

	public function subject(): string {
		return $this->subject;
	}

	public function setSubject(string $markup): void {
		$this->subject = $markup;
	}
}
