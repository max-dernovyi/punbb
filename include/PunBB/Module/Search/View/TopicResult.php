<?php

declare(strict_types=1);

namespace PunBB\Module\Search\View;

use PunBB\Module\Layout\View\Parts;

/**
 * A topic found, while its row is built: its classes, the parts of its title
 * and of the title's status, its links to pages and new posts, the parts of
 * the line below the title, the lines of its subject and its figures, and the
 * classes of the row itself.
 */
final class TopicResult {
	public readonly Parts $status;

	public readonly Parts $title;

	public readonly Parts $titleStatus;

	public readonly Parts $nav;

	public readonly Parts $subject;

	public readonly Parts $bodySubject;

	public readonly Parts $bodyInfo;

	/** The row's classes after main-item, each with the space before it. */
	public string $style = '';

	public function __construct() {
		$this->status = new Parts();
		$this->title = new Parts();
		$this->titleStatus = new Parts();
		$this->nav = new Parts();
		$this->subject = new Parts();
		$this->bodySubject = new Parts();
		$this->bodyInfo = new Parts();
	}
}
