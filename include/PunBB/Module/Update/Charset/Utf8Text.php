<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Charset;

/**
 * Text stored before 1.3, in the character set of the board's language pack,
 * with HTML entities where the pack could not store a character. Converted, it
 * is UTF-8 and holds the characters themselves.
 */
final class Utf8Text {
	/** Whether this PHP installation converts from $charset. */
	public static function knows(string $charset): bool {
		if (in_array($charset, self::mbstringEncodings(), true))
			return true;

		// iconv() is optional, and warns and returns false for a name it does not know
		return function_exists('iconv') && @iconv($charset, 'UTF-8', 'a') !== false;
	}

	/** Whether $text is well-formed UTF-8, as far as its byte patterns tell. */
	public static function seemsUtf8(string $text): bool {
		$length = strlen($text);
		for ($i = 0; $i < $length; ++$i)
		{
			$byte = ord($text[$i]);
			if ($byte < 0x80)
				continue;

			$following = match (true) {
				($byte & 0xE0) === 0xC0	=> 1,
				($byte & 0xF0) === 0xE0	=> 2,
				($byte & 0xF8) === 0xF0	=> 3,
				($byte & 0xFC) === 0xF8	=> 4,
				($byte & 0xFE) === 0xFC	=> 5,
				default					=> null,
			};

			if ($following === null)
				return false;

			for ($j = 0; $j < $following; ++$j)
				if (++$i === $length || (ord($text[$i]) & 0xC0) !== 0x80)
					return false;
		}

		return true;
	}

	/**
	 * $text in UTF-8, from $charset, with its entities, numeric ones included,
	 * made characters; null when that changes nothing.
	 *
	 * @throws ConversionException $charset cannot convert $text
	 */
	public static function convert(?string $text, string $charset): ?string {
		if ($text === null || $text === '')
			return null;

		$str = $text;

		// Literal entities, for a charset html_entity_decode() does not decode into UTF-8
		if ($charset === 'ISO-8859-15')
			$str = html_entity_decode($str, ENT_QUOTES, $charset);

		if (!self::seemsUtf8($str))
		{
			// mbstring is a hard requirement, iconv is not: iconv() only for a name mbstring does not know
			$converted = in_array($charset, self::mbstringEncodings(), true)
				? mb_convert_encoding($str, 'UTF-8', $charset)
				: (function_exists('iconv') ? @iconv($charset, 'UTF-8', $str) : false);

			// A failed conversion would blank the row it is stored into
			if ($converted === false)
				throw new ConversionException('Failed to convert a value to UTF-8 from the requested character set. Conversion aborted.');

			$str = $converted;
		}

		$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
		$str = (string) preg_replace_callback('/&#([0-9]+);/', static fn (array $matches): string => self::character((int) $matches[1]), $str);
		$str = (string) preg_replace_callback('/&#x([a-f0-9]+);/i', static fn (array $matches): string => is_int($code = hexdec($matches[1])) ? self::character($code) : '', $str);

		return $str !== $text ? $str : null;
	}

	/** The UTF-8 bytes of code point $code; '' for a surrogate, the byte order mark and a code point out of range. */
	public static function character(int $code): string {
		return match (true) {
			$code < 0										=> '',
			$code <= 0x7F									=> chr($code),
			$code <= 0x7FF									=> chr(0xC0 | ($code >> 6)).chr(0x80 | ($code & 0x3F)),
			$code === 0xFEFF, $code >= 0xD800 && $code <= 0xDFFF	=> '',
			$code <= 0xFFFF									=> chr(0xE0 | ($code >> 12)).chr(0x80 | (($code >> 6) & 0x3F)).chr(0x80 | ($code & 0x3F)),
			$code <= 0x10FFFF								=> chr(0xF0 | ($code >> 18)).chr(0x80 | (($code >> 12) & 0x3F)).chr(0x80 | (($code >> 6) & 0x3F)).chr(0x80 | ($code & 0x3F)),
			default											=> '',
		};
	}

	/** @return list<string> the encodings mbstring knows, upper-cased */
	private static function mbstringEncodings(): array {
		return array_map(strtoupper(...), mb_list_encodings());
	}
}
