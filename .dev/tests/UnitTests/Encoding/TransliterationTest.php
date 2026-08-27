<?php
/**
 * forum_ascii() and the sef_friendly() output contract.
 *
 * utf8_decode() is deprecated since PHP 8.2. It never transliterated anything
 * here: every byte it produced for a non-ASCII character was outside a-z0-9
 * and the whitelist below it dropped that byte anyway. forum_ascii() states
 * that outcome directly, so slugs stay byte-identical and existing URLs keep
 * resolving. Real transliteration lives in lang/<language>/url_replace.php.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransliterationTest extends TestCase {
	/** @var array<string, mixed> */
	private array $saved = array();

	protected function setUp(): void {
		global $forum_config, $forum_user;

		$this->saved = array('config' => $forum_config, 'user' => $forum_user);

		$forum_config['o_sef'] = 'Default';
		$forum_user['language'] = 'English';
	}

	protected function tearDown(): void {
		global $forum_config, $forum_user;

		$forum_config = $this->saved['config'];
		$forum_user = $this->saved['user'];
	}

	public static function asciiCases(): array {
		return array(
			'empty'			=> array('', ''),
			'plain ascii'	=> array('Hello World 42', 'Hello World 42'),
			'punctuation'	=> array('a-b_c.d/e', 'a-b_c.d/e'),
			'latin1 range'	=> array("Caf\u{00E9}", 'Caf?'),
			'cyrillic'		=> array("\u{041F}\u{0440}\u{0438}\u{0432}\u{0435}\u{0442}", '??????'),
			'mixed'			=> array("a\u{00FC}b\u{4E2D}c", 'a?b?c'),
			'astral'		=> array("x\u{1F600}y", 'x?y'),
		);
	}

	#[DataProvider('asciiCases')]
	public function testForumAsciiMapsEveryNonAsciiCharacterToOneMarker(string $input, string $expected): void {
		$this->assertSame($expected, forum_ascii($input));
	}

	public function testForumAsciiTreatsNullAsTheEmptyString(): void {
		$this->assertSame('', forum_ascii(null));
	}

	public function testForumAsciiLeavesTheGlobalSubstituteCharacterUntouched(): void {
		$before = mb_substitute_character();
		forum_ascii("\u{00E9}");

		$this->assertSame($before, mb_substitute_character());
	}

	public function testForumAsciiIsIdempotentOnItsOwnOutput(): void {
		$once = forum_ascii("\u{041F}\u{0440}\u{0438}");

		$this->assertSame($once, forum_ascii($once));
	}

	public static function slugCases(): array {
		return array(
			'plain'			=> array('Hello World', 'hello-world'),
			'accents'		=> array("Caf\u{00E9} \u{00FC}ber alles", 'cafe-ueber-alles'),
			'punctuation'	=> array('What?! Really... (yes)', 'what-really-yes'),
			'digits'		=> array('PunBB 1.4.4 release', 'punbb-144-release'),
			// url_replace.php carries a cyrillic table, so strtr transliterates first
			'cyrillic'		=> array("\u{041F}\u{0440}\u{0438}\u{0432}\u{0435}\u{0442}", 'privet'),
			// nothing in the table covers han, so forum_ascii marks it and the whitelist drops it
			'han'			=> array("\u{4E2D}\u{6587}", 'view'),
			'han and ascii'	=> array("PunBB \u{4E2D}\u{6587} forum", 'punbb-forum'),
			'empty'			=> array('', 'view'),
			'only marks'	=> array('!!! ???', 'view'),
		);
	}

	#[DataProvider('slugCases')]
	public function testSefFriendlyKeepsItsSlugContract(string $input, string $expected): void {
		$this->assertSame($expected, sef_friendly($input));
	}

	#[DataProvider('slugCases')]
	public function testSefFriendlyNeverEmitsAnythingOutsideTheUrlAlphabet(string $input, string $expected): void {
		$this->assertMatchesRegularExpression('/^[a-z0-9-]*$/', sef_friendly($input));
	}
}
