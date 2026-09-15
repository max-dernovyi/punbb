<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\View;

use PunBB\Module\Layout\View\Parts;

/**
 * A post in the moderation of its topic while it is built: the parts of its
 * heading, of what identifies its poster and of its message, its classes, the
 * heading of its message and the checkbox selecting it.
 */
final class PostRow {
	public readonly Parts $postIdent;

	public readonly Parts $authorIdent;

	public readonly Parts $message;

	public readonly Parts $status;

	private string $subject = '';

	private string $select = '';

	public function __construct() {
		$this->postIdent = new Parts();
		$this->authorIdent = new Parts();
		$this->message = new Parts();
		$this->status = new Parts();
	}

	public function subject(): string {
		return $this->subject;
	}

	public function setSubject(string $markup): void {
		$this->subject = $markup;
	}

	/** The checkbox selecting the post; '' for the topic's first post. */
	public function select(): string {
		return $this->select;
	}

	public function setSelect(string $markup): void {
		$this->select = $markup;
	}
}
