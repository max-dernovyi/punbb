<?php
/**
 * Contract for forum_idna_encode() / forum_idna_decode().
 *
 * The fixture table was recorded against the bundled idna_convert (IDNA2003,
 * v0.8.0) and is now asserted against the ext-intl helpers, which run UTS-46
 * over the host of the URL and nothing else. Every expectation that had to
 * change carries a comment saying why; the full list is in
 * docs/plans/20260825-04-vendored-libs-to-composer.md, tasks 3 and 4.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdnaTest extends TestCase
{
	/**
	 * The fixture table: input, encode() result, decode() result.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public static function domains(): array
	{
		return array(
			'empty'             => array('', '', ''),
			'ascii host'        => array('example.com', 'example.com', 'example.com'),
			'ascii url'         => array('http://example.com/path?a=b#f', 'http://example.com/path?a=b#f', 'http://example.com/path?a=b#f'),
			'cyrillic host'     => array('пример.рф', 'xn--e1afmkfd.xn--p1ai', 'пример.рф'),
			// Changed: only the host is converted now. idna_convert split the
			// URL on . / : ? @ and punycoded the path as well (/xn--o1agc1b).
			'cyrillic url'      => array('http://пример.рф/путь', 'http://xn--e1afmkfd.xn--p1ai/путь', 'http://пример.рф/путь'),
			'umlaut'            => array('bücher.de', 'xn--bcher-kva.de', 'bücher.de'),
			// Changed: the helper asks for non-transitional processing, so ß stays
			// ß instead of being mapped to ss - a different domain entirely.
			// Non-transitional is only ICU's default since 76, hence the explicit
			// flag in forum_idna_convert(); this fixture pins it.
			'sharp s'           => array('straße.de', 'xn--strae-oqa.de', 'straße.de'),
			// Changed: nameprep folded final sigma to σ (xn--0xahb7a), UTS-46
			// keeps ς, so encode() round-trips.
			'final sigma'       => array('όσος.gr', 'xn--0xagb9a.gr', 'όσος.gr'),
			// Changed: nameprep stripped ZWNJ/ZWJ (xn--ngbi3gb, xn--11b2ezc),
			// UTS-46 keeps them where the joining context allows.
			'zwnj'              => array("\xD8\xA8\xD9\x8A\xD8\xAC\xE2\x80\x8C\xD9\x8A.com", 'xn--ngbi3gb3804a.com', "\xD8\xA8\xD9\x8A\xD8\xAC\xE2\x80\x8C\xD9\x8A.com"),
			'zwj'               => array("\xE0\xA4\x95\xE0\xA5\x8D\xE2\x80\x8D\xE0\xA4\xB7.in", 'xn--11b2ezcw70k.in', "\xE0\xA4\x95\xE0\xA5\x8D\xE2\x80\x8D\xE0\xA4\xB7.in"),
			'cjk'               => array('例え.テスト', 'xn--r8jz45g.xn--zckzah', '例え.テスト'),
			'emoji'             => array('😀.ws', 'xn--e28h.ws', '😀.ws'),
			'already ace'       => array('xn--e1afmkfd.xn--p1ai', 'xn--e1afmkfd.xn--p1ai', 'пример.рф'),
			'already ace url'   => array('http://xn--e1afmkfd.xn--p1ai/', 'http://xn--e1afmkfd.xn--p1ai/', 'http://пример.рф/'),
			'ace label'         => array('xn--e1afmkfd', 'xn--e1afmkfd', 'пример'),
			// Empty punycode payload: rejected by UTS-46, so the input comes
			// back untouched - the same string idna_convert passed through.
			'ace empty'         => array('xn--', 'xn--', 'xn--'),
			// ⚠️ Undecodable punycode is still not rejected on the way back:
			// idn_to_utf8() decodes it into whatever the digits happen to mean,
			// exactly like the loose mode of the old library.
			'ace invalid'       => array('xn--zzz-oops', 'xn--zzz-oops', "zz\xE1\x8B\xB3z\xE1\x8B\xAF"),
			'mixed script'      => array('пример.example.com', 'xn--e1afmkfd.example.com', 'пример.example.com'),
			// Changed: UTS-46 case-folds the whole host, so the ASCII label is
			// lowercased too, and decode() no longer echoes the input back.
			// Hosts are case-insensitive, so this is a display change only.
			'uppercase'         => array('BÜCHER.DE', 'xn--bcher-kva.de', 'bücher.de'),
			'trailing dot'      => array('пример.рф.', 'xn--e1afmkfd.xn--p1ai.', 'пример.рф.'),
			// Changed: invalid UTF-8 is returned unchanged instead of being
			// replaced by the empty string - the old behaviour dropped the URL.
			'invalid bytes'     => array("ab\xFFcd.com", "ab\xFFcd.com", "ab\xFFcd.com"),
			'invalid sequence'  => array("\xC3\x28.com", "\xC3\x28.com", "\xC3\x28.com"),
			// Changed: UTS-46 rejects a label with a leading or trailing
			// hyphen, so the input is passed through instead of encoded.
			'hyphen edges'      => array('-пример-.рф', '-пример-.рф', '-пример-.рф'),
			// Changed: 78 octets is over the 63-octet label limit. UTS-46
			// refuses it, IDNA2003 emitted the over-long label anyway.
			'long label'        => array(
				'примерпримерпримерпримерпримерпримерпримерпримерпримерпримерпримерпример.рф',
				'примерпримерпримерпримерпримерпримерпримерпримерпримерпримерпримерпример.рф',
				'примерпримерпримерпримерпримерпримерпримерпримерпримерпримерпримерпример.рф',
			),
			'ip url'            => array('http://192.168.0.1/x', 'http://192.168.0.1/x', 'http://192.168.0.1/x'),
			// Changed: the user info is left alone now; idna_convert punycoded
			// every segment it split off, credentials included.
			'userinfo url'      => array('http://user:pw@пример.рф/x', 'http://user:pw@xn--e1afmkfd.xn--p1ai/x', 'http://user:pw@пример.рф/x'),
			'port url'          => array('http://пример.рф:8080/x', 'http://xn--e1afmkfd.xn--p1ai:8080/x', 'http://пример.рф:8080/x'),
			// Changed: same reason - the local part of an address is not a host.
			'email'             => array('почта@пример.рф', 'почта@xn--e1afmkfd.xn--p1ai', 'почта@пример.рф'),
			'www prefix'        => array('http://www.münchen.de', 'http://www.xn--mnchen-3ya.de', 'http://www.münchen.de'),
			'ftp url'           => array('ftp://ftp.пример.рф', 'ftp://ftp.xn--e1afmkfd.xn--p1ai', 'ftp://ftp.пример.рф'),
			'query'             => array('http://example.com/?q=привет', 'http://example.com/?q=привет', 'http://example.com/?q=привет'),
			'single label'      => array('a.b', 'a.b', 'a.b'),
			// UTS-46 maps disallowed_STD3 codepoints onto ASCII, so without the
			// host check the converted URL would carry markup delimiters into an
			// unescaped href: U+FF02 -> '"', U+FF1C/FF1E -> '<'/'>', U+FF0F -> '/'.
			'std3 mapped'       => array(
				"http://\xEF\xBC\x82\xEF\xBC\x9E\xEF\xBC\x9Csvg\xEF\xBC\x8F.example.com/",
				"http://\xEF\xBC\x82\xEF\xBC\x9E\xEF\xBC\x9Csvg\xEF\xBC\x8F.example.com/",
				"http://\xEF\xBC\x82\xEF\xBC\x9E\xEF\xBC\x9Csvg\xEF\xBC\x8F.example.com/",
			),
		);
	}

	#[DataProvider('domains')]
	public function testEncode(string $input, string $encoded, string $decoded): void
	{
		$this->assertSame($encoded, forum_idna_encode($input));
	}

	#[DataProvider('domains')]
	public function testDecode(string $input, string $encoded, string $decoded): void
	{
		$this->assertSame($decoded, forum_idna_decode($input));
	}

	/**
	 * The call sites only ever decode a URL that already starts with a
	 * punycoded host, so that path is pinned separately.
	 */
	public function testDecodeOfEncodedHostRoundTrips(): void
	{
		$this->assertSame('http://пример.рф', forum_idna_decode(forum_idna_encode('http://пример.рф')));
	}

	/**
	 * The four inputs IDNA2003 mangled during nameprep - ß, final sigma, ZWNJ
	 * and ZWJ - survive UTS-46 intact. Under the bundled library these were
	 * lossy: straße.de came back as strasse.de.
	 *
	 * @return array<string, string[]>
	 */
	public static function formerlyLossyProvider(): array
	{
		return array(
			'sharp s'     => array('straße.de'),
			'final sigma' => array('όσος.gr'),
			'zwnj'        => array("\xD8\xA8\xD9\x8A\xD8\xAC\xE2\x80\x8C\xD9\x8A.com"),
			'zwj'         => array("\xE0\xA4\x95\xE0\xA5\x8D\xE2\x80\x8D\xE0\xA4\xB7.in"),
		);
	}

	#[DataProvider('formerlyLossyProvider')]
	public function testEncodeIsNoLongerLossy(string $input): void
	{
		$this->assertSame($input, forum_idna_decode(forum_idna_encode($input)));
	}

	public function testEncodeConvertsAHost(): void
	{
		$this->assertSame('http://xn--d1acpjx3f.xn--p1ai/', forum_idna_encode('http://яндекс.рф/'));
	}

	public function testEncodeLeavesAlreadyEncodedInputAlone(): void
	{
		$this->assertSame('http://xn--d1acpjx3f.xn--p1ai/', forum_idna_encode('http://xn--d1acpjx3f.xn--p1ai/'));
	}

	public function testDecodeLeavesAlreadyDecodedInputAlone(): void
	{
		$this->assertSame('http://яндекс.рф/', forum_idna_decode('http://яндекс.рф/'));
	}

	/**
	 * Nothing the converter rejects may be dropped: the helpers hand the input
	 * back unchanged, which is what the non-strict bundled library promised.
	 *
	 * @return array<string, string[]>
	 */
	public static function unconvertibleProvider(): array
	{
		return array(
			'invalid utf-8'   => array("http://ab\xFFcd.com/x"),
			'leading hyphen'  => array('http://-пример.рф/x'),
			'empty host'      => array('http:///x'),
			'no host at all'  => array('/relative/path'),
			'query only'      => array('?a=b'),
		);
	}

	#[DataProvider('unconvertibleProvider')]
	public function testUnconvertibleInputIsReturnedUnchanged(string $input): void
	{
		$this->assertSame($input, forum_idna_encode($input));
		$this->assertSame($input, forum_idna_decode($input));
	}

	public function testEmptyInputIsReturnedUnchanged(): void
	{
		$this->assertSame('', forum_idna_encode(''));
		$this->assertSame('', forum_idna_decode(''));
	}

	/**
	 * The whole point of converting the host on its own: everything around it
	 * is left byte for byte as it came in.
	 */
	public function testOnlyTheHostIsConverted(): void
	{
		$this->assertSame(
			'https://user:pw@xn--80adxhks.xn--p1ai:8443/путь/ещё?q=привет#якорь',
			forum_idna_encode('https://user:pw@москва.рф:8443/путь/ещё?q=привет#якорь')
		);
	}
}
