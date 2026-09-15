<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Posting;

use PunBB\Module\Layout\View\Html;

/**
 * A message as a post stores it once its BBCode is checked and tidied, with
 * every error that stops it from being posted.
 */
final readonly class PreparsedMessage {
	/** @param list<Html> $errors */
	public function __construct(public string $text, public array $errors) {}
}
