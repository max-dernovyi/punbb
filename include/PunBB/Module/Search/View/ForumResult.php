<?php

declare(strict_types=1);

namespace PunBB\Module\Search\View;

use PunBB\Module\Layout\View\Parts;

/**
 * A forum subscribed to, while its row is built: the labels of its category's
 * summary when it opens one, its classes, the parts of its title and of the
 * line below it, the lines of its subject and its figures, and the classes of
 * the row itself.
 */
final class ForumResult {
	public readonly Parts $headerSubject;

	public readonly Parts $headerInfo;

	public readonly Parts $status;

	public readonly Parts $title;

	public readonly Parts $subject;

	public readonly Parts $bodySubject;

	public readonly Parts $bodyInfo;

	/** The row's classes after main-item, each with the space before it. */
	public string $style = '';

	public function __construct() {
		$this->headerSubject = new Parts();
		$this->headerInfo = new Parts();
		$this->status = new Parts();
		$this->title = new Parts();
		$this->subject = new Parts();
		$this->bodySubject = new Parts();
		$this->bodyInfo = new Parts();
	}
}
