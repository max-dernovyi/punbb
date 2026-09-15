<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Posting;

use PunBB\Module\Layout\View\Html;

/**
 * What a post's subject and message must be to be posted.
 */
interface PostRulesInterface {
	/** The longest a topic's subject may be, in characters. */
	public function subjectMaximumLength(): int;

	/** The longest a post's message may be, in bytes. */
	public function messageMaximumBytes(): int;

	/**
	 * $text with its BBCode checked and tidied, the board's settings deciding
	 * what is allowed. Its tags are checked only while $errors is empty.
	 *
	 * @param list<Html> $errors what already stops the post, which the result carries on
	 */
	public function preparse(string $text, array $errors): PreparsedMessage;

	/**
	 * $text checked and tidied as a signature, the board's signature settings
	 * deciding what is allowed.
	 *
	 * @param list<Html> $errors
	 */
	public function preparseSignature(string $text, array $errors): PreparsedMessage;
}
