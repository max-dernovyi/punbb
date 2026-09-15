<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\View;

use PunBB\Module\Layout\View\Parts;

/**
 * A topic's row in the moderation of its forum while it is built: its classes,
 * the parts of its title and of the title's status, its links to pages and new
 * posts, the parts of the line below the title, the lines of its subject and
 * its figures, and the classes of the row itself.
 */
final class TopicRow {
	public readonly Parts $status;

	public readonly Parts $title;

	public readonly Parts $titleStatus;

	public readonly Parts $nav;

	public readonly Parts $subject;

	public readonly Parts $bodySubject;

	public readonly Parts $bodyInfo;

	private string $style = '';

	public function __construct() {
		$this->status = new Parts();
		$this->title = new Parts();
		$this->titleStatus = new Parts();
		$this->nav = new Parts();
		$this->subject = new Parts();
		$this->bodySubject = new Parts();
		$this->bodyInfo = new Parts();
	}

	public function style(): string {
		return $this->style;
	}

	public function setStyle(string $style): void {
		$this->style = $style;
	}
}
