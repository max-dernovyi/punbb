<?php
/**
 * Characterisation tests for the public utf8_* API.
 *
 * Every utf8_* function the repository references keeps its name, signature
 * and return contract across the phputf8 -> mbstring shim swap. These tests
 * record what the bundled implementation returns today; the replacement has to
 * reproduce them byte for byte.
 *
 * Return values only. Diagnostics (notices, warnings, deprecations) some of
 * these functions emit on malformed input are NOT part of the contract and are
 * swallowed by silenced() - the shim is allowed to stop emitting them.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Utf8ApiTest extends TestCase
{
	/**
	 * The input matrix. Every string-in function is exercised over all of it.
	 *
	 * @return array<string, string>
	 */
	public static function strings(): array
	{
		return array(
			'empty'         => '',
			'ascii'         => 'Hello World',
			'cyrillic'      => 'Привет Мир',
			'cjk'           => '封鎖進階設定',
			'emoji'         => 'a😀b',                       // 4-byte, outside the BMP
			'combining'     => "e\xCC\x81cole",              // e + COMBINING ACUTE ACCENT
			'latin1sup'     => 'Ünïcödé Tëst',
			'greek'         => 'ΣΊΣΥΦΟΣ',                    // final sigma on lowercasing
			'sharp_s'       => 'straße',                     // uppercases to two characters
			'bad_byte'      => "abc\xFFdef",                 // byte that starts nothing
			'bad_trunc'     => "abc\xC3",                    // truncated 2-byte sequence
			'bad_cont'      => "abc\x80def",                 // stray continuation byte
			'bad_overlong'  => "abc\xC0\x80def",             // overlong NUL
			'bad_surrogate' => "abc\xED\xA0\x80def",         // CESU-8 style surrogate
			'bad_5octet'    => "abc\xF8\x84\x80\x80\x80",    // 5-octet sequence
			'specials'      => "a b\xE2\x80\x94c\xE2\x80\xA6d",
			'ws'            => " \t\n padded \r\n ",
			'zeroish'       => '0',
		);
	}

	private static function str(string $key): string
	{
		$strings = self::strings();

		return $strings[$key];
	}

	/**
	 * Runs $fn with every PHP diagnostic swallowed and returns its result.
	 *
	 * Only the return value is the contract, so a shim that stops emitting the
	 * notices phputf8 emits on malformed input still passes. Output buffers the
	 * callee leaves open are discarded too: ⚠️ utf8_from_unicode() bails out of
	 * an ob_start() without closing it on an illegal codepoint.
	 */
	private static function silenced(callable $fn): mixed
	{
		$level = ob_get_level();

		// Only phputf8's own diagnostics may be swallowed. Engine deprecations and
		// warnings are what this suite exists to catch, so they fall through.
		set_error_handler(static function (): bool {
			return TRUE;
		}, E_USER_NOTICE | E_USER_WARNING | E_USER_DEPRECATED);

		try
		{
			return $fn();
		}
		finally
		{
			restore_error_handler();

			while (ob_get_level() > $level)
				ob_end_clean();
		}
	}

	/**
	 * @return array<string, array{string, int}>
	 */
	public static function strlenProvider(): array
	{
		return array(
			'empty'         => array('empty', 0),
			'ascii'         => array('ascii', 11),
			'cyrillic'      => array('cyrillic', 10),
			'cjk'           => array('cjk', 6),
			'emoji'         => array('emoji', 3),
			'combining'     => array('combining', 6),
			'latin1sup'     => array('latin1sup', 12),
			'greek'         => array('greek', 7),
			'sharp_s'       => array('sharp_s', 6),
			// Malformed bytes each count as one character, not as zero.
			'bad_byte'      => array('bad_byte', 7),
			'bad_trunc'     => array('bad_trunc', 4),
			'bad_cont'      => array('bad_cont', 7),
			'bad_overlong'  => array('bad_overlong', 8),
			'bad_surrogate' => array('bad_surrogate', 9),
			'bad_5octet'    => array('bad_5octet', 8),
			'specials'      => array('specials', 7),
			'ws'            => array('ws', 14),
		);
	}

	#[DataProvider('strlenProvider')]
	public function testStrlen(string $key, int $expected): void
	{
		$this->assertSame($expected, utf8_strlen(self::str($key)));
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function strtolowerProvider(): array
	{
		return array(
			'empty'         => array('empty', ''),
			'ascii'         => array('ascii', 'hello world'),
			'cyrillic'      => array('cyrillic', 'привет мир'),
			'cjk'           => array('cjk', '封鎖進階設定'),
			'emoji'         => array('emoji', 'a😀b'),
			'combining'     => array('combining', "e\xCC\x81cole"),
			'latin1sup'     => array('latin1sup', 'ünïcödé tëst'),
			// Trailing sigma becomes the final form, interior ones do not.
			'greek'         => array('greek', 'σίσυφος'),
			'sharp_s'       => array('sharp_s', 'straße'),
			// Every malformed byte collapses to the mbstring substitute character.
			'bad_byte'      => array('bad_byte', 'abc?def'),
			'bad_trunc'     => array('bad_trunc', 'abc?'),
			'bad_cont'      => array('bad_cont', 'abc?def'),
			'bad_overlong'  => array('bad_overlong', 'abc??def'),
			'bad_surrogate' => array('bad_surrogate', 'abc???def'),
			'bad_5octet'    => array('bad_5octet', 'abc?????'),
			'specials'      => array('specials', "a b\xE2\x80\x94c\xE2\x80\xA6d"),
			'ws'            => array('ws', " \t\n padded \r\n "),
		);
	}

	#[DataProvider('strtolowerProvider')]
	public function testStrtolower(string $key, string $expected): void
	{
		$this->assertSame($expected, utf8_strtolower(self::str($key)));
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function strtoupperProvider(): array
	{
		return array(
			'empty'         => array('empty', ''),
			'ascii'         => array('ascii', 'HELLO WORLD'),
			'cyrillic'      => array('cyrillic', 'ПРИВЕТ МИР'),
			'cjk'           => array('cjk', '封鎖進階設定'),
			'emoji'         => array('emoji', 'A😀B'),
			'combining'     => array('combining', "E\xCC\x81COLE"),
			'latin1sup'     => array('latin1sup', 'ÜNÏCÖDÉ TËST'),
			'greek'         => array('greek', 'ΣΊΣΥΦΟΣ'),
			// One character in, two out - the length is not preserved.
			'sharp_s'       => array('sharp_s', 'STRASSE'),
			'bad_byte'      => array('bad_byte', 'ABC?DEF'),
			'bad_trunc'     => array('bad_trunc', 'ABC?'),
			'bad_cont'      => array('bad_cont', 'ABC?DEF'),
			'bad_overlong'  => array('bad_overlong', 'ABC??DEF'),
			'bad_surrogate' => array('bad_surrogate', 'ABC???DEF'),
			'bad_5octet'    => array('bad_5octet', 'ABC?????'),
			'specials'      => array('specials', "A B\xE2\x80\x94C\xE2\x80\xA6D"),
			'ws'            => array('ws', " \t\n PADDED \r\n "),
		);
	}

	#[DataProvider('strtoupperProvider')]
	public function testStrtoupper(string $key, string $expected): void
	{
		$this->assertSame($expected, utf8_strtoupper(self::str($key)));
	}

	/**
	 * @return array<string, array{string, int, int|null, string}>
	 */
	public static function substrProvider(): array
	{
		return array(
			'ascii head'          => array('ascii', 0, 3, 'Hel'),
			'ascii to end'        => array('ascii', 2, NULL, 'llo World'),
			'ascii negative'      => array('ascii', -2, NULL, 'ld'),
			'ascii negative len'  => array('ascii', -2, 1, 'l'),
			'ascii past end'      => array('ascii', 50, 3, ''),
			'ascii negative trim' => array('ascii', 0, -1, 'Hello Worl'),
			'ascii len past end'  => array('ascii', 1, 50, 'ello World'),
			'cyrillic head'       => array('cyrillic', 0, 3, 'При'),
			'cyrillic to end'     => array('cyrillic', 2, NULL, 'ивет Мир'),
			'cyrillic negative'   => array('cyrillic', -2, NULL, 'ир'),
			'cyrillic neg len'    => array('cyrillic', -2, 1, 'и'),
			'cyrillic past end'   => array('cyrillic', 50, 3, ''),
			'cyrillic neg trim'   => array('cyrillic', 0, -1, 'Привет Ми'),
			'cyrillic len over'   => array('cyrillic', 1, 50, 'ривет Мир'),
			// Offsets count characters, so the astral emoji is one step.
			'emoji head'          => array('emoji', 0, 3, 'a😀b'),
			'emoji to end'        => array('emoji', 2, NULL, 'b'),
			'emoji negative'      => array('emoji', -2, NULL, '😀b'),
			'emoji neg len'       => array('emoji', -2, 1, '😀'),
			'emoji past end'      => array('emoji', 50, 3, ''),
			'emoji neg trim'      => array('emoji', 0, -1, 'a😀'),
			'emoji len over'      => array('emoji', 1, 50, '😀b'),
			'empty head'          => array('empty', 0, 3, ''),
			'empty to end'        => array('empty', 2, NULL, ''),
			'empty negative'      => array('empty', -2, NULL, ''),
			'empty past end'      => array('empty', 50, 3, ''),
			// Malformed bytes are substituted, not dropped, so offsets hold.
			'bad head'            => array('bad_byte', 0, 3, 'abc'),
			'bad to end'          => array('bad_byte', 2, NULL, 'c?def'),
			'bad negative'        => array('bad_byte', -2, NULL, 'ef'),
			'bad neg trim'        => array('bad_byte', 0, -1, 'abc?de'),
		);
	}

	#[DataProvider('substrProvider')]
	public function testSubstr(string $key, int $offset, ?int $length, string $expected): void
	{
		$str = self::str($key);

		$this->assertSame($expected, $length === NULL ? utf8_substr($str, $offset) : utf8_substr($str, $offset, $length));
	}

	/**
	 * @return array<string, array{string, string, int|null, int|false, int|false}>
	 */
	public static function strposProvider(): array
	{
		return array(
			// key, needle, offset, expected strpos, expected strrpos
			'ascii'            => array('ascii', 'o', NULL, 4, 7),
			'ascii offset'     => array('ascii', 'o', 5, 7, 7),
			'ascii missing'    => array('ascii', 'z', NULL, FALSE, FALSE),
			'cyrillic'         => array('cyrillic', 'и', NULL, 2, 8),
			'cyrillic offset'  => array('cyrillic', 'и', 3, 8, 8),
			'emoji'            => array('emoji', '😀', NULL, 1, 1),
			'empty haystack'   => array('empty', 'a', NULL, FALSE, FALSE),
			// An empty needle is a position, not a failure.
			'empty needle'     => array('ascii', '', NULL, 0, 11),
			// '0' is not an empty haystack: phputf8's empty() guard made strrpos() say FALSE.
			'zeroish'          => array('zeroish', '0', NULL, 0, 0),
		);
	}

	/**
	 * @param int|false $expectedPos
	 * @param int|false $expectedRpos
	 */
	#[DataProvider('strposProvider')]
	public function testStrposAndStrrpos(string $key, string $needle, ?int $offset, $expectedPos, $expectedRpos): void
	{
		$str = self::str($key);

		$this->assertSame($expectedPos, $offset === NULL ? utf8_strpos($str, $needle) : utf8_strpos($str, $needle, $offset));
		$this->assertSame($expectedRpos, $offset === NULL ? utf8_strrpos($str, $needle) : utf8_strrpos($str, $needle, $offset));
	}

	public function testStristr(): void
	{
		$this->assertSame('World', utf8_stristr(self::str('ascii'), 'WOR'));
		// An empty needle returns the whole subject rather than FALSE.
		$this->assertSame('Hello World', utf8_stristr(self::str('ascii'), ''));
		$this->assertFalse(utf8_stristr(self::str('ascii'), 'zz'));
		$this->assertSame('Мир', utf8_stristr(self::str('cyrillic'), 'МИР'));
		$this->assertSame('😀b', utf8_stristr(self::str('emoji'), '😀'));
		// Malformed bytes in the returned tail come back as they went in.
		$this->assertSame("A\xFFb", utf8_stristr("A\xFFb", 'a'));
		$this->assertSame("\xFFb", utf8_stristr("A\xFFb", "\xFF"));
	}

	public function testIreplace(): void
	{
		$this->assertSame('Hello there', utf8_ireplace('WORLD', 'there', self::str('ascii')));
		// An empty needle is a no-op, not an infinite loop.
		$this->assertSame('Hello World', utf8_ireplace('', 'x', self::str('ascii')));
		$this->assertSame('Привет world', utf8_ireplace('МИР', 'world', self::str('cyrillic')));
		$this->assertSame('He--- W-r-d', utf8_ireplace(array('l', 'o'), '-', self::str('ascii')));
		// A short replacement array drops the unmatched needles entirely.
		$this->assertSame('He11 Wr1d', utf8_ireplace(array('l', 'o'), array('1'), self::str('ascii')));
		$this->assertSame('HeLlo World', utf8_ireplace('l', 'L', self::str('ascii'), 1));
		// Same as utf8_stristr(): the unreplaced bytes are preserved verbatim.
		$this->assertSame("\xFFX", utf8_ireplace('a', 'X', "\xFFa"));
		$this->assertSame("A\xFFX", utf8_ireplace('B', 'X', "A\xFFb"));
	}

	public function testSubstrReplace(): void
	{
		$this->assertSame('Xello World', utf8_substr_replace(self::str('ascii'), 'X', 0, 1));
		$this->assertSame('Hello X', utf8_substr_replace(self::str('ascii'), 'X', 6));
		$this->assertSame('ПРИВЕТ Мир', utf8_substr_replace(self::str('cyrillic'), 'ПРИВЕТ', 0, 6));
		$this->assertSame('a!b', utf8_substr_replace(self::str('emoji'), '!', 1, 1));
		$this->assertSame('Hello WoXd', utf8_substr_replace(self::str('ascii'), 'X', -3, 2));
		$this->assertSame('X', utf8_substr_replace(self::str('empty'), 'X', 0, 0));
	}

	public function testTrim(): void
	{
		$this->assertSame('padded', utf8_trim(self::str('ws')));
		$this->assertSame('', utf8_trim(self::str('empty')));
		$this->assertSame('a', utf8_trim('xxaxx', 'x'));
		$this->assertSame('ЁмаЙО', utf8_trim(' ЁмаЙО '));
		// A multi-byte charlist, and '-' quoted so it is not a range.
		$this->assertSame('e', utf8_trim("\xc5\x98e-", "\xc5\x98-"));
		$this->assertSame('a', utf8_trim('--a--', '-'));
	}

	/**
	 * Bootstrap-loaded in phputf8 too, so extensions reach both names.
	 */
	public function testLtrimAndRtrim(): void
	{
		$this->assertSame("padded \r\n ", utf8_ltrim(self::str('ws')));
		$this->assertSame(" \t\n padded", utf8_rtrim(self::str('ws')));
		$this->assertSame('', utf8_ltrim(self::str('empty')));
		$this->assertSame('', utf8_rtrim(self::str('empty')));
		$this->assertSame('axx', utf8_ltrim('xxaxx', 'x'));
		$this->assertSame('xxa', utf8_rtrim('xxaxx', 'x'));
		// A multi-byte charlist, and '-' quoted so it is not a range.
		$this->assertSame("e-", utf8_ltrim("\xc5\x98e-", "\xc5\x98-"));
		$this->assertSame("\xc5\x98e", utf8_rtrim("\xc5\x98e-", "\xc5\x98-"));
		// The /u pattern fails wholesale on malformed input: preg_replace() returns NULL.
		$this->assertNull(utf8_ltrim("\xFFa", 'x'));
		$this->assertNull(utf8_rtrim("a\xFF", 'x'));
	}

	public function testStrspn(): void
	{
		$this->assertSame(2, utf8_strspn('42 apples', '1234567890'));
		$this->assertSame(2, utf8_strspn('аб42', 'аб'));
		$this->assertSame(0, utf8_strspn('zzz', 'a'));
		$this->assertSame(0, utf8_strspn('', 'a'));
		$this->assertSame(2, utf8_strspn('aaabbb', 'a', 1));
		$this->assertSame(2, utf8_strspn('aaabbb', 'a', 1, 2));
		// '-' is escaped by the mask quoting, so it never forms a range.
		$this->assertSame(0, utf8_strspn('a-b', '-'));
		$this->assertSame(1, utf8_strspn('-ab', '-'));
		// $length alone leaves $start at NULL; it must not reach mb_substr() as one.
		$this->assertSame(3, utf8_strspn('aaabbb', 'a', NULL, 3));
	}

	public function testStrrev(): void
	{
		$this->assertSame('', utf8_strrev(self::str('empty')));
		$this->assertSame('dlroW olleH', utf8_strrev(self::str('ascii')));
		$this->assertSame('риМ тевирП', utf8_strrev(self::str('cyrillic')));
		$this->assertSame('定設階進鎖封', utf8_strrev(self::str('cjk')));
		$this->assertSame('b😀a', utf8_strrev(self::str('emoji')));
		// The combining mark reverses away from its base character.
		$this->assertSame("eloc\xCC\x81e", utf8_strrev(self::str('combining')));
		$this->assertSame('e' . "\xC3\x9F" . 'arts', utf8_strrev(self::str('sharp_s')));
		// The /u match fails wholesale on malformed input, so nothing survives.
		$this->assertSame('', utf8_strrev(self::str('bad_byte')));
		$this->assertSame('', utf8_strrev(self::str('bad_trunc')));
		$this->assertSame('', utf8_strrev(self::str('bad_5octet')));
	}

	public function testUcwords(): void
	{
		$this->assertSame('', utf8_ucwords(self::str('empty')));
		$this->assertSame('Hello World', utf8_ucwords(self::str('ascii')));
		$this->assertSame('Привет Мир', utf8_ucwords(self::str('cyrillic')));
		$this->assertSame('封鎖進階設定', utf8_ucwords(self::str('cjk')));
		$this->assertSame('A😀b', utf8_ucwords(self::str('emoji')));
		$this->assertSame('E' . "\xCC\x81" . 'cole', utf8_ucwords(self::str('combining')));
		$this->assertSame('Ünïcödé Tëst', utf8_ucwords(self::str('latin1sup')));
		$this->assertSame('Straße', utf8_ucwords(self::str('sharp_s')));
		// Em dash and ellipsis are not word separators.
		$this->assertSame("A B\xE2\x80\x94c\xE2\x80\xA6d", utf8_ucwords(self::str('specials')));
		$this->assertSame(" \t\n Padded \r\n ", utf8_ucwords(self::str('ws')));
	}

	#[DataProvider('badStringProvider')]
	public function testUcwordsReturnsNullOnMalformedInput(string $key): void
	{
		// preg_replace_callback with /u fails on invalid UTF-8 and returns NULL.
		// forum_remove_bad_characters() strips such input before it gets here.
		$this->assertNull(self::silenced(static fn () => utf8_ucwords(self::str($key))));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function badStringProvider(): array
	{
		return array(
			'bad_byte'      => array('bad_byte'),
			'bad_trunc'     => array('bad_trunc'),
			'bad_cont'      => array('bad_cont'),
			'bad_overlong'  => array('bad_overlong'),
			'bad_surrogate' => array('bad_surrogate'),
			'bad_5octet'    => array('bad_5octet'),
		);
	}

	public function testUcwordsCallback(): void
	{
		// The match array utf8_ucwords hands over: full, (^|ws), (ws), first char.
		$this->assertSame('  Foo', utf8_ucwords_callback(array('  foo', '  ', '  ', 'f')));
		$this->assertSame('Foo', utf8_ucwords_callback(array('foo', '', '', 'f')));
		$this->assertSame('  Мир', utf8_ucwords_callback(array('  мир', '  ', '  ', 'м')));
		// A match array short of group 3 loses the leading whitespace.
		$this->assertSame('foo', self::silenced(static fn () => utf8_ucwords_callback(array('  foo', '  ', 'f'))));
	}

	public function testUcfirst(): void
	{
		$this->assertSame('', utf8_ucfirst(self::str('empty')));
		$this->assertSame('Hello World', utf8_ucfirst(self::str('ascii')));
		$this->assertSame('Привет Мир', utf8_ucfirst(self::str('cyrillic')));
		$this->assertSame('封鎖進階設定', utf8_ucfirst(self::str('cjk')));
		$this->assertSame('A😀b', utf8_ucfirst(self::str('emoji')));
		$this->assertSame('E' . "\xCC\x81" . 'cole', utf8_ucfirst(self::str('combining')));
		$this->assertSame('Ünïcödé Tëst', utf8_ucfirst(self::str('latin1sup')));
		$this->assertSame('Straße', utf8_ucfirst(self::str('sharp_s')));
		$this->assertSame('A', utf8_ucfirst('a'));
	}

	#[DataProvider('badStringProvider')]
	public function testUcfirstReturnsEmptyStringOnMalformedInput(string $key): void
	{
		// The /u preg_match fails, $matches stays empty and the concatenation
		// yields ''. phputf8 emits notices doing so; the shim need not.
		$this->assertSame('', self::silenced(static fn () => utf8_ucfirst(self::str($key))));
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function isAsciiProvider(): array
	{
		return array(
			'empty'         => array('empty', TRUE),
			'ascii'         => array('ascii', TRUE),
			// Control characters and whitespace are still ASCII.
			'ws'            => array('ws', TRUE),
			'cyrillic'      => array('cyrillic', FALSE),
			'cjk'           => array('cjk', FALSE),
			'emoji'         => array('emoji', FALSE),
			'combining'     => array('combining', FALSE),
			'latin1sup'     => array('latin1sup', FALSE),
			'greek'         => array('greek', FALSE),
			'sharp_s'       => array('sharp_s', FALSE),
			'bad_byte'      => array('bad_byte', FALSE),
			'bad_trunc'     => array('bad_trunc', FALSE),
			'bad_cont'      => array('bad_cont', FALSE),
			'bad_overlong'  => array('bad_overlong', FALSE),
			'bad_surrogate' => array('bad_surrogate', FALSE),
			'bad_5octet'    => array('bad_5octet', FALSE),
			'specials'      => array('specials', FALSE),
		);
	}

	#[DataProvider('isAsciiProvider')]
	public function testIsAscii(string $key, bool $expected): void
	{
		$this->assertSame($expected, utf8_is_ascii(self::str($key)));
	}

	/**
	 * utf8_strip_non_ascii and utf8_strip_non_ascii_ctrl agree on every input
	 * in the matrix - the only difference is the ASCII device control codes,
	 * and 'ws' carries none of the ones they disagree about.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function stripNonAsciiProvider(): array
	{
		return array(
			'empty'         => array('empty', ''),
			'ascii'         => array('ascii', 'Hello World'),
			'cyrillic'      => array('cyrillic', ' '),
			'cjk'           => array('cjk', ''),
			'emoji'         => array('emoji', 'ab'),
			'combining'     => array('combining', 'ecole'),
			'latin1sup'     => array('latin1sup', 'ncd Tst'),
			'greek'         => array('greek', ''),
			'sharp_s'       => array('sharp_s', 'strae'),
			'bad_byte'      => array('bad_byte', 'abcdef'),
			'bad_trunc'     => array('bad_trunc', 'abc'),
			'bad_cont'      => array('bad_cont', 'abcdef'),
			'bad_overlong'  => array('bad_overlong', 'abcdef'),
			'bad_surrogate' => array('bad_surrogate', 'abcdef'),
			'bad_5octet'    => array('bad_5octet', 'abc'),
			'specials'      => array('specials', 'a bcd'),
			'ws'            => array('ws', " \t\n padded \r\n "),
		);
	}

	#[DataProvider('stripNonAsciiProvider')]
	public function testStripNonAscii(string $key, string $expected): void
	{
		$this->assertSame($expected, utf8_strip_non_ascii(self::str($key)));
	}

	#[DataProvider('stripNonAsciiProvider')]
	public function testStripNonAsciiCtrl(string $key, string $expected): void
	{
		$this->assertSame($expected, utf8_strip_non_ascii_ctrl(self::str($key)));
	}

	public function testStripNonAsciiCtrlRemovesDeviceControlCodes(): void
	{
		// This is where the two strippers part company: \x00 and \x1B are ASCII
		// but not permitted, so only the _ctrl variant drops them.
		$this->assertSame("a\x00b\x1Bc", utf8_strip_non_ascii("a\x00b\x1Bc"));
		$this->assertSame('abc', utf8_strip_non_ascii_ctrl("a\x00b\x1Bc"));
	}

	public function testStripSpecials(): void
	{
		$this->assertSame('', utf8_strip_specials(self::str('empty')));
		$this->assertSame('HelloWorld', utf8_strip_specials(self::str('ascii')));
		$this->assertSame('ПриветМир', utf8_strip_specials(self::str('cyrillic')));
		$this->assertSame('封鎖進階設定', utf8_strip_specials(self::str('cjk')));
		// The emoji is not in the specials table and survives.
		$this->assertSame('a😀b', utf8_strip_specials(self::str('emoji')));
		$this->assertSame('ecole', utf8_strip_specials(self::str('combining')));
		$this->assertSame('ÜnïcödéTëst', utf8_strip_specials(self::str('latin1sup')));
		$this->assertSame('ΣΊΣΥΦΟΣ', utf8_strip_specials(self::str('greek')));
		$this->assertSame('straße', utf8_strip_specials(self::str('sharp_s')));
		$this->assertSame('abcd', utf8_strip_specials(self::str('specials')));
		$this->assertSame('padded', utf8_strip_specials(self::str('ws')));
		$this->assertSame('a-b', utf8_strip_specials('a-b'));
		$this->assertSame('a_b', utf8_strip_specials('a_b'));
		$this->assertSame('a.b', utf8_strip_specials('a.b'));
		$this->assertSame('a:b', utf8_strip_specials('a:b'));
		$this->assertSame('aXb', utf8_strip_specials('a b', 'X'));
	}

	#[DataProvider('badStringProvider')]
	public function testStripSpecialsReturnsNullOnMalformedInput(string $key): void
	{
		// preg_replace with /u fails on invalid UTF-8 and returns NULL.
		$this->assertNull(self::silenced(static fn () => utf8_strip_specials(self::str($key))));
	}

	public function testSpecialsPattern(): void
	{
		$pattern = utf8_specials_pattern();

		$this->assertStringStartsWith('/[\x00-\x19', $pattern);
		$this->assertStringEndsWith(']/u', $pattern);
		// The exact pattern is the contract: it decides what counts as a word
		// character forum-wide. Pinned so any drift in the table is loud.
		$this->assertSame(1313, strlen($pattern));
		$this->assertSame('96f0b3adb1f99819e79195f13de9ef24', md5($pattern));

		$this->assertSame(1, preg_match($pattern, ' '));
		$this->assertSame(1, preg_match($pattern, "\x00"));
		$this->assertSame(1, preg_match($pattern, "\xE2\x80\xA6"));
		$this->assertSame(0, preg_match($pattern, 'a'));
		$this->assertSame(0, preg_match($pattern, 'ф'));
		// Cached in a static, so repeated calls return the identical string.
		$this->assertSame($pattern, utf8_specials_pattern());
	}

	public function testToUnicode(): void
	{
		$this->assertSame(array(), utf8_to_unicode(self::str('empty')));
		$this->assertSame(array(72, 101, 108, 108, 111, 32, 87, 111, 114, 108, 100), utf8_to_unicode(self::str('ascii')));
		$this->assertSame(array(1055, 1088, 1080, 1074, 1077, 1090, 32, 1052, 1080, 1088), utf8_to_unicode(self::str('cyrillic')));
		$this->assertSame(array(97, 128512, 98), utf8_to_unicode(self::str('emoji')));
		// The BOM is decoded away rather than emitted as a codepoint.
		$this->assertSame(array(65), utf8_to_unicode("\xEF\xBB\xBFA"));
	}

	public function testToUnicodeRejectsMalformedInput(): void
	{
		$this->assertFalse(self::silenced(static fn () => utf8_to_unicode(self::str('bad_byte'))));
		$this->assertFalse(self::silenced(static fn () => utf8_to_unicode(self::str('bad_cont'))));
		$this->assertFalse(self::silenced(static fn () => utf8_to_unicode(self::str('bad_surrogate'))));
		$this->assertFalse(self::silenced(static fn () => utf8_to_unicode(self::str('bad_overlong'))));
		// ⚠️ A sequence truncated at the very end is dropped silently instead of
		// failing - there is no end-of-string state check, unlike bad_identify.
		$this->assertSame(array(97, 98, 99), self::silenced(static fn () => utf8_to_unicode(self::str('bad_trunc'))));
	}

	public function testFromUnicode(): void
	{
		$this->assertSame('', utf8_from_unicode(array()));
		$this->assertSame('HeФ😀', utf8_from_unicode(array(72, 101, 0x424, 0x1F600)));
		// The BOM is dropped on the way out too.
		$this->assertSame('A', utf8_from_unicode(array(0xFEFF, 65)));
		$this->assertSame("\x00", utf8_from_unicode(array(0)));
	}

	public function testFromUnicodeRejectsIllegalCodepoints(): void
	{
		$this->assertFalse(self::silenced(static fn () => utf8_from_unicode(array(0xD800))));
		$this->assertFalse(self::silenced(static fn () => utf8_from_unicode(array(0xDFFF))));
		$this->assertFalse(self::silenced(static fn () => utf8_from_unicode(array(0x110000))));
	}

	public function testToUnicodeAndFromUnicodeRoundTrip(): void
	{
		foreach (array('empty', 'ascii', 'cyrillic', 'cjk', 'emoji', 'combining', 'latin1sup', 'greek', 'sharp_s', 'specials', 'ws') as $key)
		{
			$str = self::str($key);
			$this->assertSame($str, utf8_from_unicode(utf8_to_unicode($str)), $key);
		}
	}

	/**
	 * @return array<string, array{string, int|false, int|null}>
	 */
	public static function badIdentifyProvider(): array
	{
		return array(
			'empty'         => array('empty', FALSE, NULL),
			'ascii'         => array('ascii', FALSE, NULL),
			'cyrillic'      => array('cyrillic', FALSE, NULL),
			'cjk'           => array('cjk', FALSE, NULL),
			'emoji'         => array('emoji', FALSE, NULL),
			'combining'     => array('combining', FALSE, NULL),
			'latin1sup'     => array('latin1sup', FALSE, NULL),
			'greek'         => array('greek', FALSE, NULL),
			'sharp_s'       => array('sharp_s', FALSE, NULL),
			'specials'      => array('specials', FALSE, NULL),
			'ws'            => array('ws', FALSE, NULL),
			'bad_byte'      => array('bad_byte', UTF8_BAD_SEQID, 3),
			'bad_cont'      => array('bad_cont', UTF8_BAD_SEQID, 3),
			'bad_trunc'     => array('bad_trunc', UTF8_BAD_SEQINCOMPLETE, 3),
			'bad_overlong'  => array('bad_overlong', UTF8_BAD_NONSHORT, 4),
			'bad_surrogate' => array('bad_surrogate', UTF8_BAD_SURROGATE, 5),
			'bad_5octet'    => array('bad_5octet', UTF8_BAD_5OCTET, 3),
		);
	}

	/**
	 * @param int|false $expectedCode
	 */
	#[DataProvider('badIdentifyProvider')]
	public function testBadIdentify(string $key, $expectedCode, ?int $expectedIndex): void
	{
		$i = -1;

		// $i is by reference and carries the byte index of the offending byte,
		// or NULL when the string is clean.
		$this->assertSame($expectedCode, utf8_bad_identify(self::str($key), $i));
		$this->assertSame($expectedIndex, $i);
	}

	public function testBadIdentifySixOctetSequence(): void
	{
		$i = -1;

		$this->assertSame(UTF8_BAD_6OCTET, utf8_bad_identify("abc\xFC\x84\x80\x80\x80\x80", $i));
		$this->assertSame(3, $i);
	}

	public function testBadIdentifyReturnCodesAreDistinct(): void
	{
		$codes = array(UTF8_BAD_5OCTET, UTF8_BAD_6OCTET, UTF8_BAD_SEQID, UTF8_BAD_NONSHORT,
			UTF8_BAD_SURROGATE, UTF8_BAD_UNIOUTRANGE, UTF8_BAD_SEQINCOMPLETE);

		$this->assertSame(array(1, 2, 3, 4, 5, 6, 7), $codes);
	}

	/**
	 * Byte offsets, not character offsets - the one place the shim is most
	 * likely to drift, so every boundary in the matrix is pinned.
	 *
	 * @return array<string, array{string, int, int, int}>
	 */
	public static function locateProvider(): array
	{
		return array(
			// key, byte index, expected current, expected next
			'ascii negative'     => array('ascii', -1, 0, 0),
			'ascii zero'         => array('ascii', 0, 0, 0),
			'ascii mid'          => array('ascii', 5, 5, 5),
			'ascii at end'       => array('ascii', 11, 11, 11),
			'ascii past end'     => array('ascii', 16, 11, 11),
			'cyrillic zero'      => array('cyrillic', 0, 0, 0),
			// Index 1 sits on a continuation byte: current steps back, next forward.
			'cyrillic mid char'  => array('cyrillic', 1, 0, 2),
			'cyrillic boundary'  => array('cyrillic', 2, 2, 2),
			'cyrillic mid char2' => array('cyrillic', 3, 2, 4),
			'cyrillic mid char3' => array('cyrillic', 5, 4, 6),
			'cyrillic at end'    => array('cyrillic', 19, 19, 19),
			'cyrillic past end'  => array('cyrillic', 24, 19, 19),
			'emoji zero'         => array('emoji', 0, 0, 0),
			'emoji boundary'     => array('emoji', 1, 1, 1),
			// Inside the 4-byte sequence, so next jumps the whole character.
			'emoji inside'       => array('emoji', 2, 1, 5),
			'emoji inside2'      => array('emoji', 3, 1, 5),
			'emoji after'        => array('emoji', 5, 5, 5),
			'emoji at end'       => array('emoji', 6, 6, 6),
			'emoji past end'     => array('emoji', 11, 6, 6),
			'empty negative'     => array('empty', -1, 0, 0),
			'empty zero'         => array('empty', 0, 0, 0),
			'empty past end'     => array('empty', 5, 0, 0),
		);
	}

	#[DataProvider('locateProvider')]
	public function testLocateCurrentAndNextChr(string $key, int $idx, int $expectedCurrent, int $expectedNext): void
	{
		$str = self::str($key);

		$this->assertSame($expectedCurrent, utf8_locate_current_chr($str, $idx), 'current');
		$this->assertSame($expectedNext, utf8_locate_next_chr($str, $idx), 'next');
	}

	public function testLocateChrDoesNotModifyTheString(): void
	{
		// Both take the string by reference; neither may touch it.
		$str = self::str('cyrillic');
		utf8_locate_current_chr($str, 3);
		utf8_locate_next_chr($str, 3);

		$this->assertSame(self::str('cyrillic'), $str);
	}

	/**
	 * The frozen public API. Extensions call these names directly, so the shim
	 * has to keep every one of them resolvable.
	 */
	public function testPublicApiIsComplete(): void
	{
		$api = array(
			'utf8_bad_identify', 'utf8_from_unicode', 'utf8_ireplace', 'utf8_is_ascii',
			'utf8_locate_current_chr', 'utf8_locate_next_chr', 'utf8_ltrim', 'utf8_ord',
			'utf8_rtrim', 'utf8_specials_pattern', 'utf8_strip_non_ascii',
			'utf8_strip_non_ascii_ctrl', 'utf8_strip_specials', 'utf8_stristr',
			'utf8_strlen', 'utf8_strpos', 'utf8_strrev', 'utf8_strrpos', 'utf8_strspn',
			'utf8_strtolower', 'utf8_strtoupper', 'utf8_substr', 'utf8_substr_replace',
			'utf8_to_unicode', 'utf8_trim', 'utf8_ucfirst', 'utf8_ucwords',
			'utf8_ucwords_callback',
		);

		foreach ($api as $fn)
			$this->assertTrue(function_exists($fn), $fn.'() must stay resolvable');

		// Both directions: a name silently added to the shim is drift too.
		$this->assertSame($api, self::declaredUtf8Functions());
	}

	/**
	 * Every utf8_* function include/utf8.php defines, sorted.
	 *
	 * @return array<int, string>
	 */
	private static function declaredUtf8Functions(): array
	{
		preg_match_all(
			'/^function\s+(utf8_[a-z0-9_]+)\s*\(/mi',
			(string) file_get_contents(FORUM_ROOT.'include/utf8.php'),
			$matches
		);

		$names = array_unique($matches[1]);
		sort($names);

		return array_values($names);
	}
}
