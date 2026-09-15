<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Format;

use PunBB\Module\Layout\View\Html;

/**
 * Values shown the way the visitor reads them: in their time zone and date
 * format, with their language's number separators.
 */
interface FormatterInterface {
	/** The moment, or "Today" and "Yesterday" from the language pack: markup. */
	public function time(int $timestamp, TimeFormat $format): Html;

	/** The number with the language pack's separators, which are markup. */
	public function number(int|float $number, int $decimals = 0): Html;

	/**
	 * The title shown beside a user: their own, a ban, their group's, their rank's, or the default.
	 */
	public function memberTitle(string $username, string $title, int $posts, ?int $groupId, ?string $groupTitle): Html;

	/** "Users: 1 to 50 of 51" for a listing of $pages pages, $first to $last of $total. */
	public function itemsInfo(Html $label, int $first, int $last, int $total, int $pages): Html;

	/** @return array<string, string> each smiley's text => its image under img/smilies/ */
	public function smilies(): array;

	/** A post's text, its BBCode and smilies turned into markup as the visitor's settings show them. */
	public function message(string $text, bool $hideSmilies): Html;

	/** $text with the board's censored words replaced: text in, text out. */
	public function censor(string $text): string;

	/** A signature's text, its BBCode turned into markup as the board allows it in signatures. */
	public function signature(string $text): Html;

	/**
	 * Member $userId's avatar of image type $type at $width by $height, named $username; empty for none.
	 *
	 * @param bool $fresh whether the address makes a browser fetch the image again, as after an upload
	 */
	public function avatar(int $userId, int $type, int $width, int $height, string $username, bool $fresh = false): Html;

	/** The current moment in $pattern, a date() format, in the visitor's time zone: the example beside a format's setting. A date and time takes $pattern for the date. */
	public function now(TimeFormat $format, string $pattern): Html;

	/** @return array<int, string> the time formats a member chooses from, by the number their profile stores; 0 is the board's */
	public function timeFormats(): array;

	/** @return array<int, string> the date formats a member chooses from, by the number their profile stores; 0 is the board's */
	public function dateFormats(): array;
}
