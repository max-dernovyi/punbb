<?php

declare(strict_types=1);

namespace PunBB\Module\Index\View;

use PunBB\Module\Layout\View\Parts;

/**
 * A forum's row on the board index while it is built: its classes, the parts
 * of its title and of the line below it, its moderators, the lines of its
 * subject and its figures, and the classes of the row itself.
 */
final class ForumRow {
	public readonly Parts $status;

	public readonly Parts $title;

	public readonly Parts $subject;

	public readonly Parts $moderators;

	public readonly Parts $bodySubject;

	public readonly Parts $bodyInfo;

	private string $style = '';

	public function __construct() {
		$this->status = new Parts();
		$this->title = new Parts();
		$this->subject = new Parts();
		$this->moderators = new Parts();
		$this->bodySubject = new Parts();
		$this->bodyInfo = new Parts();
	}

	/** The row's classes after main-item, each with the space before it. */
	public function style(): string {
		return $this->style;
	}

	public function setStyle(string $style): void {
		$this->style = $style;
	}
}
