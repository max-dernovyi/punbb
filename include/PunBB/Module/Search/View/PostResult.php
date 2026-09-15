<?php

declare(strict_types=1);

namespace PunBB\Module\Search\View;

use PunBB\Module\Layout\View\Parts;

/**
 * A post found, while its block is built: the parts of its heading, its
 * classes and its links, and the markup of its title, its author and its text.
 */
final class PostResult {
	public readonly Parts $ident;

	public readonly Parts $status;

	public readonly Parts $actions;

	public string $subject = '';

	public string $author = '';

	public string $message = '';

	public function __construct() {
		$this->ident = new Parts();
		$this->status = new Parts();
		$this->actions = new Parts();
	}
}
