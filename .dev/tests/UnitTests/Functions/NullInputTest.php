<?php
/**
 * The shared string helpers on PHP 8.1+, where passing null to an internal
 * function parameter is deprecated.
 *
 * They take a string and return a string; an absent request key or a nullable
 * column reaching one of them is the empty string, not a deprecation. The suite
 * runs with failOnDeprecation, so a regression fails these tests by itself.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class NullInputTest extends TestCase {
	public function testHtmlencodeEscapesAString(): void {
		$this->assertEquals('&lt;b&gt;x&lt;/b&gt;', forum_htmlencode('<b>x</b>'));
		$this->assertEquals('&quot;q&quot; &amp; &#039;a&#039;', forum_htmlencode('"q" & \'a\''));
	}

	public function testHtmlencodeTreatsNullAsEmpty(): void {
		$absent = array();

		$this->assertSame('', forum_htmlencode(null));
		$this->assertSame('', forum_htmlencode($absent['missing'] ?? null));
	}

	public function testTrimStripsWhitespaceAndNonBreakingSpace(): void {
		$this->assertEquals('x', forum_trim("  x\t\n"));
		$this->assertEquals('x', forum_trim("\xC2\xA0x\xC2\xA0"));
		$this->assertEquals('a b', forum_trim(' a b '));
	}

	public function testTrimTreatsNullAsEmpty(): void {
		$this->assertSame('', forum_trim(null));
	}

	public function testLinebreaksNormalisesEveryLineEnding(): void {
		$this->assertEquals("a\nb\nc", forum_linebreaks("a\r\nb\rc"));
	}

	public function testLinebreaksTreatsNullAsEmpty(): void {
		$this->assertSame('', forum_linebreaks(null));
	}

	public function testCensorWordsReturnsAStringForNull(): void {
		global $forum_censors;

		// Skip the cache branch: the suite must behave the same on a checkout
		// with no generated cache and no database to regenerate it from.
		if (!defined('FORUM_CENSORS_LOADED'))
			define('FORUM_CENSORS_LOADED', 1);

		$forum_censors = array(
			0 => array('id' => '1', 'search_for' => 'badword', 'replace_with' => 'XXX'),
		);

		$this->assertEquals('a XXX here', censor_words('a badword here'));
		$this->assertSame('', censor_words(null));
	}

	/**
	 * A request parameter sent as name[]=x arrives as an array. PHP 8 turns that
	 * into a TypeError inside the helper, so the contract covers it too.
	 */
	public function testTheHelpersTreatAnArrayAsEmpty(): void {
		$array = array('x');

		$this->assertSame('', forum_htmlencode($array));
		$this->assertSame('', forum_trim($array));
		$this->assertSame('', forum_linebreaks($array));
	}

	public function testTheHelpersStillAcceptNumbers(): void {
		$this->assertSame('42', forum_htmlencode(42));
		$this->assertSame('42', forum_trim(42));
		$this->assertSame('4.5', forum_linebreaks(4.5));
	}

	/**
	 * The installer indexes the first post before $forum_user exists, so the
	 * stopword lookup has to cope with no language at all.
	 */
	public function testValidateSearchWordWorksWithoutAUserLanguage(): void {
		global $forum_user;

		$this->assertArrayNotHasKey('language', $forum_user);

		$this->assertTrue(validate_search_word('elephant'));
		$this->assertFalse(validate_search_word('a'));
	}
}
