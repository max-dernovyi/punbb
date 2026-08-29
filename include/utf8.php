<?php
/**
 * UTF-8 string handling.
 *
 * Replaces the bundled phputf8 library with ext-mbstring and PCRE. The utf8_*
 * names are public API - extensions call them directly - so every name,
 * signature and return value is kept; only the implementation changed.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

// Set once here so no mb_* call below has to pass an encoding. Extensions
// calling mb_* without one get UTF-8 too.
mb_internal_encoding('UTF-8');

// Every pattern in this file needs the /u modifier.
if (preg_match('/^.{1}$/u', 'ñ') !== 1)
	exit('PCRE is not compiled with UTF-8 support');

/**
 * Return codes of utf8_bad_identify().
 */
define('UTF8_BAD_5OCTET', 1);			// 5 octet sequence - always illegal
define('UTF8_BAD_6OCTET', 2);			// 6 octet sequence - always illegal
define('UTF8_BAD_SEQID', 3);			// not a legal first octet
define('UTF8_BAD_NONSHORT', 4);			// not the shortest form
define('UTF8_BAD_SURROGATE', 5);		// UTF-16 surrogate half
define('UTF8_BAD_UNIOUTRANGE', 6);		// codepoint above U+10FFFF
define('UTF8_BAD_SEQINCOMPLETE', 7);	// multi-octet sequence cut short


/**
 * Number of characters in a UTF-8 string. Malformed bytes count as one
 * character each rather than being ignored.
 *
 * @param string $str
 * @return int
 */
function utf8_strlen($str)
{
	return mb_strlen($str);
}


/**
 * Position of the first occurrence of $search, in characters.
 *
 * @param string $str
 * @param string $search
 * @param int|false $offset
 * @return int|false
 */
function utf8_strpos($str, $search, $offset = FALSE)
{
	// NULL is not an offset: mb_strpos() takes a non-nullable int and deprecates it.
	if ($offset === FALSE || $offset === NULL)
		return mb_strpos($str, $search);

	return mb_strpos($str, $search, $offset);
}


/**
 * Position of the last occurrence of $search, in characters.
 *
 * @param string $str
 * @param string $search
 * @param int|false $offset
 * @return int|false
 */
function utf8_strrpos($str, $search, $offset = FALSE)
{
	if ($offset === FALSE)
	{
		// Emulate strrpos() rather than let mb_strrpos() complain
		if ($str === '')
			return FALSE;

		return mb_strrpos($str, $search);
	}

	if (!is_int($offset))
		return FALSE;

	$pos = mb_strrpos(mb_substr($str, $offset), $search);

	return $pos === FALSE ? FALSE : $pos + $offset;
}


/**
 * Part of a string, offset and length counted in characters.
 *
 * @param string $str
 * @param int $offset
 * @param int|false|null $length
 * @return string
 */
function utf8_substr($str, $offset, $length = FALSE)
{
	if ($length === FALSE)
		return mb_substr($str, $offset);

	return mb_substr($str, $offset, $length);
}


/**
 * @param string $str
 * @return string
 */
function utf8_strtolower($str)
{
	return mb_strtolower($str);
}


/**
 * @param string $str
 * @return string
 */
function utf8_strtoupper($str)
{
	return mb_strtoupper($str);
}


/**
 * Case-insensitive strstr().
 *
 * @param string $str
 * @param string $search
 * @return string|false
 */
function utf8_stristr($str, $search)
{
	if ($search === '')
		return $str;

	// mb_stristr() substitutes malformed bytes with '?'; the frozen contract
	// returns the tail of the original string byte for byte, so a broken input
	// is located on a lowercased copy and sliced out of the untouched bytes.
	if (!mb_check_encoding($str) || !mb_check_encoding($search))
	{
		$lstr = mb_strtolower($str);
		$lsearch = mb_strtolower($search);

		if (preg_match('/^(.*)'.preg_quote($lsearch, '/').'/Us', $lstr, $matches) !== 1)
			return FALSE;

		return substr($str, strlen($matches[1]));
	}

	return mb_stristr($str, $search);
}


/**
 * Case-insensitive str_replace(). $count is a limit on the number of
 * replacements, not an out parameter.
 *
 * @param string|array<int|string, string> $search
 * @param string|array<int|string, string> $replace
 * @param string $str
 * @param int|null $count
 * @return string
 */
function utf8_ireplace($search, $replace, $str, $count = NULL)
{
	if (is_array($search))
	{
		foreach (array_keys($search) as $k)
		{
			if (!is_array($replace))
				$str = utf8_ireplace($search[$k], $replace, $str, $count);
			else
				$str = utf8_ireplace($search[$k], array_key_exists($k, $replace) ? $replace[$k] : '', $str, $count);
		}

		return $str;
	}

	if ($search === '')
		return $str;

	// Same reason as utf8_stristr(): the unreplaced parts of a malformed string
	// must come back as the bytes that went in, which mb_substr() cannot do.
	// Matched on a lowercased copy, spliced into the original bytes.
	if (!mb_check_encoding($str) || !mb_check_encoding($search))
	{
		$slen = strlen($search);
		$lendif = strlen($replace) - $slen;
		$pattern = '/(.*)'.preg_quote(mb_strtolower($search), '/').'/Us';
		$lstr = mb_strtolower($str);
		$matched = 0;
		$i = 0;

		while (preg_match($pattern, $lstr, $matches) === 1)
		{
			if ($i === $count)
				break;

			$mlen = strlen($matches[0]);
			$lstr = substr($lstr, $mlen);
			$str = substr_replace($str, $replace, $matched + strlen($matches[1]), $slen);
			$matched += $mlen + $lendif;
			$i++;
		}

		return $str;
	}

	$result = '';
	$offset = 0;
	$length = mb_strlen($search);
	$i = 0;

	while (($pos = mb_stripos($str, $search, $offset)) !== FALSE)
	{
		if ($i === $count)
			break;

		$result .= mb_substr($str, $offset, $pos - $offset).$replace;
		$offset = $pos + $length;
		$i++;
	}

	return $result.mb_substr($str, $offset);
}


/**
 * substr_replace(), offsets and lengths counted in characters.
 *
 * @param string $str
 * @param string $repl
 * @param int $start
 * @param int|null $length
 * @return string
 */
function utf8_substr_replace($str, $repl, $start, $length = NULL)
{
	$chars = mb_str_split($str);

	if ($length === NULL)
		$length = count($chars);

	array_splice($chars, $start, $length, mb_str_split($repl));

	return implode('', $chars);
}


/**
 * ltrim() with a UTF-8 aware $charlist.
 *
 * @param string $str
 * @param string|false $charlist
 * @return string|null
 */
function utf8_ltrim($str, $charlist = FALSE)
{
	if ($charlist === FALSE)
		return ltrim($str);

	return preg_replace('#^['.preg_quote($charlist, '#').']+#u', '', $str);
}


/**
 * rtrim() with a UTF-8 aware $charlist.
 *
 * @param string $str
 * @param string|false $charlist
 * @return string|null
 */
function utf8_rtrim($str, $charlist = FALSE)
{
	if ($charlist === FALSE)
		return rtrim($str);

	return preg_replace('#['.preg_quote($charlist, '#').']+$#u', '', $str);
}


/**
 * trim() with a UTF-8 aware $charlist.
 *
 * @param string $str
 * @param string|false $charlist
 * @return string|null
 */
function utf8_trim($str, $charlist = FALSE)
{
	if ($charlist === FALSE)
		return trim($str);

	$charlist = preg_quote($charlist, '#');

	return preg_replace('#^['.$charlist.']+|['.$charlist.']+$#u', '', $str);
}


/**
 * Length of the initial segment of $str consisting only of characters in
 * $mask, in characters.
 *
 * @param string $str
 * @param string $mask
 * @param int|null $start
 * @param int|null $length
 * @return int
 */
function utf8_strspn($str, $mask, $start = NULL, $length = NULL)
{
	$mask = preg_replace('!([\\\\\\-\\]\\[/^])!', '\\\${1}', $mask);

	// $start stays optional when only $length is given; mb_substr() deprecates the NULL.
	if ($start !== NULL || $length !== NULL)
		$str = utf8_substr($str, $start === NULL ? 0 : $start, $length);

	if (preg_match('/^['.$mask.']+/u', $str, $matches) !== 1)
		return 0;

	return mb_strlen($matches[0]);
}


/**
 * Reverse a string, character by character.
 *
 * Returns '' on malformed input: the /u match fails wholesale, so nothing is
 * recovered. Kept as it was - forum_remove_bad_characters() runs first.
 *
 * @param string $str
 * @return string
 */
function utf8_strrev($str)
{
	preg_match_all('/./us', $str, $matches);

	return implode('', array_reverse($matches[0]));
}


/**
 * Uppercase the first character of every word.
 *
 * Returns NULL on malformed input, like the preg_replace_callback() it is.
 *
 * @param string $str
 * @return string|null
 */
function utf8_ucwords($str)
{
	// [\x0c\x09\x0b\x0a\x0d\x20] - form feed, horizontal tab, vertical tab,
	// linefeed, carriage return and space, the word separators ucwords() uses
	$pattern = '/(^|([\x0c\x09\x0b\x0a\x0d\x20]+))([^\x0c\x09\x0b\x0a\x0d\x20]{1})[^\x0c\x09\x0b\x0a\x0d\x20]*/u';

	return preg_replace_callback($pattern, 'utf8_ucwords_callback', $str);
}


/**
 * Callback of utf8_ucwords(). Public because extensions pass it to preg_* too.
 *
 * @param array<int, string> $matches
 * @return string
 */
function utf8_ucwords_callback($matches)
{
	$leading_ws = isset($matches[2]) ? $matches[2] : '';
	$first = isset($matches[3]) ? $matches[3] : '';
	$word = ltrim($matches[0]);

	return $leading_ws.mb_strtoupper($first).mb_substr($word, 1);
}


/**
 * Uppercase the first character of a string.
 *
 * Returns '' on malformed input - the /u match fails and there is no first
 * character to work with.
 *
 * @param string $str
 * @return string
 */
function utf8_ucfirst($str)
{
	if (mb_strlen($str) < 2)
		return mb_strtoupper($str);

	if (preg_match('/^(.{1})(.*)$/us', $str, $matches) !== 1)
		return '';

	return mb_strtoupper($matches[1]).$matches[2];
}


/**
 * TRUE if the string holds nothing but 7bit ASCII bytes.
 *
 * @param string $str
 * @return bool
 */
function utf8_is_ascii($str)
{
	return preg_match('/[^\x00-\x7F]/', $str) !== 1;
}


/**
 * Strip every non-ASCII byte.
 *
 * @param string $str
 * @return string
 */
function utf8_strip_non_ascii($str)
{
	return preg_replace('/[^\x00-\x7F]/', '', $str);
}


/**
 * Strip every non-ASCII byte and every ASCII device control code.
 *
 * @param string $str
 * @return string
 */
function utf8_strip_non_ascii_ctrl($str)
{
	return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $str);
}


/**
 * PCRE pattern matching the "special" (non-word) characters of the local
 * charsets, plus the ASCII control codes. It decides what counts as a word
 * character forum-wide, so the table is frozen.
 *
 * @return string
 */
function utf8_specials_pattern()
{
	static $pattern = NULL;

	if (!$pattern)
	{
		$specials = array(
	0x001a, 0x001b, 0x001c, 0x001d, 0x001e, 0x001f, 0x0020, 0x0021, 0x0022, 0x0023,
	0x0024, 0x0025, 0x0026, 0x0027, 0x0028, 0x0029, 0x002a, 0x002b, 0x002c,
	0x002f,         0x003b, 0x003c, 0x003d, 0x003e, 0x003f, 0x0040, 0x005b,
	0x005c, 0x005d, 0x005e,         0x0060, 0x007b, 0x007c, 0x007d, 0x007e,
	0x007f, 0x0080, 0x0081, 0x0082, 0x0083, 0x0084, 0x0085, 0x0086, 0x0087, 0x0088,
	0x0089, 0x008a, 0x008b, 0x008c, 0x008d, 0x008e, 0x008f, 0x0090, 0x0091, 0x0092,
	0x0093, 0x0094, 0x0095, 0x0096, 0x0097, 0x0098, 0x0099, 0x009a, 0x009b, 0x009c,
	0x009d, 0x009e, 0x009f, 0x00a0, 0x00a1, 0x00a2, 0x00a3, 0x00a4, 0x00a5, 0x00a6,
	0x00a7, 0x00a8, 0x00a9, 0x00aa, 0x00ab, 0x00ac, 0x00ad, 0x00ae, 0x00af, 0x00b0,
	0x00b1, 0x00b2, 0x00b3, 0x00b4, 0x00b5, 0x00b6, 0x00b7, 0x00b8, 0x00b9, 0x00ba,
	0x00bb, 0x00bc, 0x00bd, 0x00be, 0x00bf, 0x00d7, 0x00f7, 0x02c7, 0x02d8, 0x02d9,
	0x02da, 0x02db, 0x02dc, 0x02dd, 0x0300, 0x0301, 0x0303, 0x0309, 0x0323, 0x0384,
	0x0385, 0x0387, 0x03b2, 0x03c6, 0x03d1, 0x03d2, 0x03d5, 0x03d6, 0x05b0, 0x05b1,
	0x05b2, 0x05b3, 0x05b4, 0x05b5, 0x05b6, 0x05b7, 0x05b8, 0x05b9, 0x05bb, 0x05bc,
	0x05bd, 0x05be, 0x05bf, 0x05c0, 0x05c1, 0x05c2, 0x05c3, 0x05f3, 0x05f4, 0x060c,
	0x061b, 0x061f, 0x0640, 0x064b, 0x064c, 0x064d, 0x064e, 0x064f, 0x0650, 0x0651,
	0x0652, 0x066a, 0x0e3f, 0x200c, 0x200d, 0x200e, 0x200f, 0x2013, 0x2014, 0x2015,
	0x2017, 0x2018, 0x2019, 0x201a, 0x201c, 0x201d, 0x201e, 0x2020, 0x2021, 0x2022,
	0x2026, 0x2030, 0x2032, 0x2033, 0x2039, 0x203a, 0x2044, 0x20a7, 0x20aa, 0x20ab,
	0x20ac, 0x2116, 0x2118, 0x2122, 0x2126, 0x2135, 0x2190, 0x2191, 0x2192, 0x2193,
	0x2194, 0x2195, 0x21b5, 0x21d0, 0x21d1, 0x21d2, 0x21d3, 0x21d4, 0x2200, 0x2202,
	0x2203, 0x2205, 0x2206, 0x2207, 0x2208, 0x2209, 0x220b, 0x220f, 0x2211, 0x2212,
	0x2215, 0x2217, 0x2219, 0x221a, 0x221d, 0x221e, 0x2220, 0x2227, 0x2228, 0x2229,
	0x222a, 0x222b, 0x2234, 0x223c, 0x2245, 0x2248, 0x2260, 0x2261, 0x2264, 0x2265,
	0x2282, 0x2283, 0x2284, 0x2286, 0x2287, 0x2295, 0x2297, 0x22a5, 0x22c5, 0x2310,
	0x2320, 0x2321, 0x2329, 0x232a, 0x2469, 0x2500, 0x2502, 0x250c, 0x2510, 0x2514,
	0x2518, 0x251c, 0x2524, 0x252c, 0x2534, 0x253c, 0x2550, 0x2551, 0x2552, 0x2553,
	0x2554, 0x2555, 0x2556, 0x2557, 0x2558, 0x2559, 0x255a, 0x255b, 0x255c, 0x255d,
	0x255e, 0x255f, 0x2560, 0x2561, 0x2562, 0x2563, 0x2564, 0x2565, 0x2566, 0x2567,
	0x2568, 0x2569, 0x256a, 0x256b, 0x256c, 0x2580, 0x2584, 0x2588, 0x258c, 0x2590,
	0x2591, 0x2592, 0x2593, 0x25a0, 0x25b2, 0x25bc, 0x25c6, 0x25ca, 0x25cf, 0x25d7,
	0x2605, 0x260e, 0x261b, 0x261e, 0x2660, 0x2663, 0x2665, 0x2666, 0x2701, 0x2702,
	0x2703, 0x2704, 0x2706, 0x2707, 0x2708, 0x2709, 0x270c, 0x270d, 0x270e, 0x270f,
	0x2710, 0x2711, 0x2712, 0x2713, 0x2714, 0x2715, 0x2716, 0x2717, 0x2718, 0x2719,
	0x271a, 0x271b, 0x271c, 0x271d, 0x271e, 0x271f, 0x2720, 0x2721, 0x2722, 0x2723,
	0x2724, 0x2725, 0x2726, 0x2727, 0x2729, 0x272a, 0x272b, 0x272c, 0x272d, 0x272e,
	0x272f, 0x2730, 0x2731, 0x2732, 0x2733, 0x2734, 0x2735, 0x2736, 0x2737, 0x2738,
	0x2739, 0x273a, 0x273b, 0x273c, 0x273d, 0x273e, 0x273f, 0x2740, 0x2741, 0x2742,
	0x2743, 0x2744, 0x2745, 0x2746, 0x2747, 0x2748, 0x2749, 0x274a, 0x274b, 0x274d,
	0x274f, 0x2750, 0x2751, 0x2752, 0x2756, 0x2758, 0x2759, 0x275a, 0x275b, 0x275c,
	0x275d, 0x275e, 0x2761, 0x2762, 0x2763, 0x2764, 0x2765, 0x2766, 0x2767, 0x277f,
	0x2789, 0x2793, 0x2794, 0x2798, 0x2799, 0x279a, 0x279b, 0x279c, 0x279d, 0x279e,
	0x279f, 0x27a0, 0x27a1, 0x27a2, 0x27a3, 0x27a4, 0x27a5, 0x27a6, 0x27a7, 0x27a8,
	0x27a9, 0x27aa, 0x27ab, 0x27ac, 0x27ad, 0x27ae, 0x27af, 0x27b1, 0x27b2, 0x27b3,
	0x27b4, 0x27b5, 0x27b6, 0x27b7, 0x27b8, 0x27b9, 0x27ba, 0x27bb, 0x27bc, 0x27bd,
	0x27be, 0xf6d9, 0xf6da, 0xf6db, 0xf8d7, 0xf8d8, 0xf8d9, 0xf8da, 0xf8db, 0xf8dc,
	0xf8dd, 0xf8de, 0xf8df, 0xf8e0, 0xf8e1, 0xf8e2, 0xf8e3, 0xf8e4, 0xf8e5, 0xf8e6,
	0xf8e7, 0xf8e8, 0xf8e9, 0xf8ea, 0xf8eb, 0xf8ec, 0xf8ed, 0xf8ee, 0xf8ef, 0xf8f0,
	0xf8f1, 0xf8f2, 0xf8f3, 0xf8f4, 0xf8f5, 0xf8f6, 0xf8f7, 0xf8f8, 0xf8f9, 0xf8fa,
	0xf8fb, 0xf8fc, 0xf8fd, 0xf8fe, 0xfe7c, 0xfe7d,
		);

		$pattern = '/[\x00-\x19'.preg_quote(utf8_from_unicode($specials), '/').']/u';
	}

	return $pattern;
}


/**
 * Remove the "special" characters utf8_specials_pattern() matches.
 *
 * Returns NULL on malformed input, like the preg_replace() it is.
 *
 * @param string $string
 * @param string $repl
 * @return string|null
 */
function utf8_strip_specials($string, $repl = '')
{
	return preg_replace(utf8_specials_pattern(), $repl, $string);
}


/**
 * Codepoints of a UTF-8 string. Occurrences of the BOM are dropped.
 *
 * Returns FALSE on malformed input, except for a multi-byte sequence cut off
 * at the very end of the string, which is dropped silently.
 *
 * @param string $str
 * @return array<int, int>|false
 */
function utf8_to_unicode($str)
{
	$len = strlen($str);
	$start = $len - 1;

	// Walk back over the continuation bytes of the trailing sequence
	while ($start >= 0 && (ord($str[$start]) & 0xC0) === 0x80)
		$start--;

	if ($start >= 0)
	{
		$lead = ord($str[$start]);

		if ($lead >= 0xC0 && $lead <= 0xFD)
		{
			if ($lead >= 0xFC)
				$expected = 6;
			else if ($lead >= 0xF8)
				$expected = 5;
			else if ($lead >= 0xF0)
				$expected = 4;
			else if ($lead >= 0xE0)
				$expected = 3;
			else
				$expected = 2;

			// Cut short by the end of the string, so drop it
			if ($expected > $len - $start)
				$str = substr($str, 0, $start);
		}
	}

	$i = NULL;

	if (utf8_bad_identify($str, $i) !== FALSE)
		return FALSE;

	$out = array();

	foreach (mb_str_split($str) as $chr)
	{
		$code = mb_ord($chr);

		// The BOM is legal but is never part of the output
		if ($code !== 0xFEFF)
			$out[] = $code;
	}

	return $out;
}


/**
 * UTF-8 string from an array of codepoints. The BOM is dropped.
 *
 * Returns FALSE on a surrogate half or a codepoint outside Unicode.
 *
 * @param array<int|string, int> $arr
 * @return string|false
 */
function utf8_from_unicode($arr)
{
	$out = '';

	foreach ($arr as $code)
	{
		if ($code === 0xFEFF)
			continue;

		if ($code < 0 || $code > 0x10FFFF || ($code >= 0xD800 && $code <= 0xDFFF))
			return FALSE;

		$out .= mb_chr($code);
	}

	return $out;
}


/**
 * Find the first byte that breaks UTF-8.
 *
 * $i takes the byte index of the offending sequence, or NULL when the string
 * is well formed.
 *
 * @param string $str
 * @param int|null $i
 * @return int|false one of the UTF8_BAD_* codes, or FALSE when clean
 */
function utf8_bad_identify($str, &$i)
{
	if (mb_check_encoding($str, 'UTF-8'))
	{
		$i = NULL;
		return FALSE;
	}

	// Something is wrong - decode by hand to say what and where
	$state = 0;		// continuation octets still expected
	$ucs4 = 0;		// codepoint being assembled
	$bytes = 1;		// octets in the sequence being assembled
	$len = strlen($str);

	for ($i = 0; $i < $len; $i++)
	{
		$in = ord($str[$i]);

		if ($state == 0)
		{
			// Either US-ASCII or the first octet of a multi-octet sequence
			if ((0x80 & $in) == 0)
			{
				$bytes = 1;
			}
			else if ((0xE0 & $in) == 0xC0)
			{
				$ucs4 = ($in & 0x1F) << 6;
				$state = 1;
				$bytes = 2;
			}
			else if ((0xF0 & $in) == 0xE0)
			{
				$ucs4 = ($in & 0x0F) << 12;
				$state = 2;
				$bytes = 3;
			}
			else if ((0xF8 & $in) == 0xF0)
			{
				$ucs4 = ($in & 0x07) << 18;
				$state = 3;
				$bytes = 4;
			}
			// 5 and 6 octet sequences encode either a non-shortest form or a
			// codepoint outside Unicode, so both are illegal on sight
			else if ((0xFC & $in) == 0xF8)
				return UTF8_BAD_5OCTET;
			else if ((0xFE & $in) == 0xFC)
				return UTF8_BAD_6OCTET;
			else
				return UTF8_BAD_SEQID;
		}
		else if ((0xC0 & $in) == 0x80)
		{
			// Legal continuation octet
			$ucs4 |= ($in & 0x3F) << (($state - 1) * 6);

			if (--$state == 0)
			{
				// From Unicode 3.1 the non-shortest form is illegal
				if (($bytes == 2 && $ucs4 < 0x80) || ($bytes == 3 && $ucs4 < 0x800) || ($bytes == 4 && $ucs4 < 0x10000))
					return UTF8_BAD_NONSHORT;
				// From Unicode 3.2 surrogates are illegal
				else if (($ucs4 & 0xFFFFF800) == 0xD800)
					return UTF8_BAD_SURROGATE;
				else if ($ucs4 > 0x10FFFF)
					return UTF8_BAD_UNIOUTRANGE;

				$ucs4 = 0;
				$bytes = 1;
			}
		}
		else
		{
			// Multi-octet sequence broken off by a non-continuation octet
			$i--;
			return UTF8_BAD_SEQINCOMPLETE;
		}
	}

	if ($state != 0)
	{
		$i--;
		return UTF8_BAD_SEQINCOMPLETE;
	}

	// Unreachable: mb_check_encoding() already said the string is broken
	$i = NULL;
	return FALSE;
}


/**
 * Byte index where the UTF-8 character covering $idx starts. Steps backwards
 * over continuation bytes; $idx itself is returned when it is a boundary.
 *
 * @param string $str
 * @param int $idx byte index
 * @return int byte index
 */
function utf8_locate_current_chr(&$str, $idx)
{
	if ($idx <= 0)
		return 0;

	$limit = strlen($str);

	if ($idx >= $limit)
		return $limit;

	// Every byte after the first of a multi-byte character is 10xxxxxx
	while ($idx && (ord($str[$idx]) & 0xC0) == 0x80)
		$idx--;

	return $idx;
}


/**
 * Byte index where the next UTF-8 character starts. Steps forward over
 * continuation bytes; $idx itself is returned when it is a boundary.
 *
 * @param string $str
 * @param int $idx byte index
 * @return int byte index
 */
function utf8_locate_next_chr(&$str, $idx)
{
	if ($idx <= 0)
		return 0;

	$limit = strlen($str);

	if ($idx >= $limit)
		return $limit;

	while ($idx < $limit && (ord($str[$idx]) & 0xC0) == 0x80)
		$idx++;

	return $idx;
}


/**
 * Codepoint of a UTF-8 character. Unlike mb_ord() this also decodes the 5 and
 * 6 octet forms, which are no longer valid UTF-8 but still turn up in old data.
 *
 * @param string $chr
 * @return int|false
 */
function utf8_ord($chr)
{
	$ord0 = $chr === '' ? 0 : ord($chr[0]);

	if ($ord0 >= 0 && $ord0 <= 127)
		return $ord0;

	if (!isset($chr[1]))
	{
		trigger_error('Short sequence - at least 2 bytes expected, only 1 seen');
		return FALSE;
	}

	$ord1 = ord($chr[1]);

	if ($ord0 >= 192 && $ord0 <= 223)
		return ($ord0 - 192) * 64 + ($ord1 - 128);

	if (!isset($chr[2]))
	{
		trigger_error('Short sequence - at least 3 bytes expected, only 2 seen');
		return FALSE;
	}

	$ord2 = ord($chr[2]);

	if ($ord0 >= 224 && $ord0 <= 239)
		return ($ord0 - 224) * 4096 + ($ord1 - 128) * 64 + ($ord2 - 128);

	if (!isset($chr[3]))
	{
		trigger_error('Short sequence - at least 4 bytes expected, only 3 seen');
		return FALSE;
	}

	$ord3 = ord($chr[3]);

	if ($ord0 >= 240 && $ord0 <= 247)
		return ($ord0 - 240) * 262144 + ($ord1 - 128) * 4096 + ($ord2 - 128) * 64 + ($ord3 - 128);

	if (!isset($chr[4]))
	{
		trigger_error('Short sequence - at least 5 bytes expected, only 4 seen');
		return FALSE;
	}

	$ord4 = ord($chr[4]);

	if ($ord0 >= 248 && $ord0 <= 251)
		return ($ord0 - 248) * 16777216 + ($ord1 - 128) * 262144 + ($ord2 - 128) * 4096 + ($ord3 - 128) * 64 + ($ord4 - 128);

	if (!isset($chr[5]))
	{
		trigger_error('Short sequence - at least 6 bytes expected, only 5 seen');
		return FALSE;
	}

	if ($ord0 >= 252 && $ord0 <= 253)
		return ($ord0 - 252) * 1073741824 + ($ord1 - 128) * 16777216 + ($ord2 - 128) * 262144 + ($ord3 - 128) * 4096 + ($ord4 - 128) * 64 + (ord($chr[5]) - 128);

	if ($ord0 >= 254)
		trigger_error('Invalid UTF-8 with surrogate ordinal '.$ord0);

	return FALSE;
}
