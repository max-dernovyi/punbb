<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;

/**
 * The formats of include/functions.php and include/parser.php, with the
 * extension code attached to them.
 */
final class LegacyFormatter implements FormatterInterface {
	public function time(int $timestamp, TimeFormat $format): Html {
		return new Html(Markers::markup(\format_time($timestamp, match ($format) {
			TimeFormat::DateTime	=> \FORUM_FT_DATETIME,
			TimeFormat::Date		=> \FORUM_FT_DATE,
			TimeFormat::Time		=> \FORUM_FT_TIME,
		})));
	}

	public function number(int|float $number, int $decimals = 0): Html {
		return new Html(Markers::markup(\forum_number_format($number, $decimals)));
	}

	public function memberTitle(string $username, string $title, int $posts, ?int $groupId, ?string $groupTitle): Html {
		return new Html(Markers::markup(\get_title(array(
			'username'		=> $username,
			'title'			=> $title,
			'num_posts'		=> $posts,
			'g_id'			=> $groupId,
			'g_user_title'	=> $groupTitle,
		))));
	}

	public function itemsInfo(Html $label, int $first, int $last, int $total, int $pages): Html {
		// generate_items_info() reads the page count and the last item from the page
		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();
		$page['num_pages'] = $pages;
		$page['finish_at'] = $last;
		$GLOBALS['forum_page'] = $page;

		return new Html(Markers::markup(\generate_items_info($label->html, $first, $total)));
	}

	public function smilies(): array {
		self::loadParser();

		$smilies = array();
		foreach (is_array($GLOBALS['smilies'] ?? null) ? $GLOBALS['smilies'] : array() as $text => $image)
			$smilies[(string) $text] = Markers::markup($image);

		return $smilies;
	}

	public function message(string $text, bool $hideSmilies): Html {
		self::loadParser();

		return new Html(Markers::markup(\parse_message($text, $hideSmilies ? '1' : '0')));
	}

	public function censor(string $text): string {
		return Markers::markup(\censor_words($text));
	}

	public function signature(string $text): Html {
		self::loadParser();

		return new Html(Markers::markup(\parse_signature($text)));
	}

	public function avatar(int $userId, int $type, int $width, int $height, string $username, bool $fresh = false): Html {
		return new Html(Markers::markup(\generate_avatar_markup($userId, $type, $width, $height, $username, $fresh)));
	}

	public function now(TimeFormat $format, string $pattern): Html {
		return new Html(Markers::markup(match ($format) {
			TimeFormat::Time		=> \format_time(time(), \FORUM_FT_TIME, null, $pattern),
			TimeFormat::Date		=> \format_time(time(), \FORUM_FT_DATE, $pattern, null, true),
			TimeFormat::DateTime	=> \format_time(time(), \FORUM_FT_DATETIME, $pattern, null, true),
		}));
	}

	public function timeFormats(): array {
		return self::formats('forum_time_formats');
	}

	public function dateFormats(): array {
		return self::formats('forum_date_formats');
	}

	/** @return array<int, string> the formats include/common.php listed in the global $name */
	private static function formats(string $name): array {
		$formats = array();
		foreach (is_array($GLOBALS[$name] ?? null) ? $GLOBALS[$name] : array() as $key => $format)
			$formats[(int) $key] = Markers::markup($format);

		return $formats;
	}

	private static function loadParser(): void {
		if (!defined('FORUM_PARSER_LOADED'))
			LegacyScope::requireGlobally(LegacyChromeSource::root().'include/parser.php');
	}
}
