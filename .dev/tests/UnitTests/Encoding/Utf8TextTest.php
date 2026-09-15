<?php
/**
 * The updater's conversion of text a 1.2 board stored: what reads as UTF-8,
 * a character set converted, entities made characters, and the code points a
 * numeric entity cannot name.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Update\Charset\ConversionException;
use PunBB\Module\Update\Charset\Utf8Text;

class Utf8TextTest extends TestCase {
	public function testWhatReadsAsUtf8(): void {
		$this->assertTrue(Utf8Text::seemsUtf8('plain ASCII'));
		$this->assertTrue(Utf8Text::seemsUtf8('Ärger, Привет, 𝄞'));
		$this->assertFalse(Utf8Text::seemsUtf8("Caf\xE9"));
		$this->assertFalse(Utf8Text::seemsUtf8("\xC3"), 'a sequence cut short');
		$this->assertFalse(Utf8Text::seemsUtf8("\xFF"));
	}

	/** @return array<string, array{?string, string, ?string}> */
	public static function conversionProvider(): array {
		return array(
			'Latin-1'					=> array("J\xF6rg", 'ISO-8859-1', 'Jörg'),
			'Latin-9 with its euro'		=> array("\xA4 5", 'ISO-8859-15', '€ 5'),
			'Windows-1251'				=> array("\xCF\xF0\xE8\xE2\xE5\xF2", 'WINDOWS-1251', 'Привет'),
			'named entities'			=> array('K&ouml;ln &amp; Bonn', 'ISO-8859-1', 'Köln & Bonn'),
			'numeric entities'			=> array('&#8364; &#x20AC; &#65;', 'ISO-8859-1', '€ € A'),
			'a surrogate'				=> array('a&#55296;b&#xD800;c', 'ISO-8859-1', 'abc'),
			'out of range'				=> array('a&#1114112;b', 'ISO-8859-1', 'ab'),
			'hex beyond an int'			=> array('a&#x10000000000000041;b', 'ISO-8859-1', 'ab'),
			'UTF-8 already'				=> array('Jörg', 'ISO-8859-1', null),
			'empty'						=> array('', 'ISO-8859-1', null),
			'NULL'						=> array(null, 'ISO-8859-1', null),
		);
	}

	#[DataProvider('conversionProvider')]
	public function testTextIsConvertedOrLeftAsItIs(?string $text, string $charset, ?string $expected): void {
		$this->assertSame($expected, Utf8Text::convert($text, $charset));
	}

	public function testEveryCodePointIsEncodedAsMbstringEncodesIt(): void {
		foreach (array(0x41, 0x7F, 0x80, 0x7FF, 0x800, 0xFFFD, 0x10000, 0x10FFFF) as $code)
			$this->assertSame(mb_chr($code, 'UTF-8'), Utf8Text::character($code), sprintf('U+%X', $code));
	}

	public function testTheByteOrderMarkIsDropped(): void {
		$this->assertSame('', Utf8Text::character(0xFEFF));
	}

	/** A value no converter reads would be stored blank: the update stops instead. */
	public function testAConversionThatFailsStopsTheUpdate(): void {
		$this->expectException(ConversionException::class);
		$this->expectExceptionMessage('Failed to convert a value to UTF-8 from the requested character set. Conversion aborted.');

		Utf8Text::convert("J\xF6rg", 'NO-SUCH-SET');
	}

	public function testOnlyACharacterSetThisPhpConvertsIsKnown(): void {
		$this->assertTrue(Utf8Text::knows('ISO-8859-1'));
		$this->assertTrue(Utf8Text::knows('WINDOWS-1251'));
		$this->assertFalse(Utf8Text::knows('NO-SUCH-SET'));
	}
}
