<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Posting\PreparsedMessage;

/**
 * The limits include/constants.php and include/essentials.php define, and
 * preparse_bbcode() of include/parser.php, with the extension code attached to it.
 */
final class LegacyPostRules implements PostRulesInterface {
	public function subjectMaximumLength(): int {
		return (int) Markers::markup(\FORUM_SUBJECT_MAXIMUM_LENGTH);
	}

	/** include/essentials.php defines it unless config.php did. */
	public function messageMaximumBytes(): int {
		return (int) Markers::markup(constant('FORUM_MAX_POSTSIZE_BYTES'));
	}

	public function preparse(string $text, array $errors): PreparsedMessage {
		return self::preparsed($text, $errors, false);
	}

	public function preparseSignature(string $text, array $errors): PreparsedMessage {
		return self::preparsed($text, $errors, true);
	}

	/** @param list<Html> $errors */
	private static function preparsed(string $text, array $errors, bool $signature): PreparsedMessage {
		if (!defined('FORUM_PARSER_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/parser.php');

		$messages = array_map(static fn (Html $error): string => $error->html, $errors);
		$preparsed = \preparse_bbcode($text, $messages, $signature);

		return new PreparsedMessage(Markers::markup($preparsed), array_values(array_map(static fn (mixed $error): Html => new Html(Markers::markup($error)), is_array($messages) ? $messages : array())));
	}
}
