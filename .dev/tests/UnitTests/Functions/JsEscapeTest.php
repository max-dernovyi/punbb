<?php
/**
 * forum_js_escape() — the escaper for a value that lands inside a JavaScript
 * string literal in an inline <script>.
 *
 * The board title reached `label: "<!-- forum_board_title -->"` in main.tpl
 * through forum_htmlencode(), and the admin settings form accepts any string.
 * htmlspecialchars() is the wrong tool there: a <script> element is raw text,
 * so the entities it emits never decode, while the one character that does
 * matter to a JavaScript string — the backslash — is not on its list. A title
 * ending in `\` escaped the closing quote and the literal swallowed the rest of
 * the block.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JsEscapeTest extends TestCase
{
	public static function hostileValues(): array
	{
		return array(
			'trailing backslash'	=> array('PunBB\\'),
			'inner backslash'		=> array('a\\nb'),
			'double quote'			=> array('say "hi"'),
			'single quote'			=> array("it's"),
			'script end tag'		=> array('</script><script>alert(1)</script>'),
			'newline'				=> array("one\ntwo"),
			'carriage return'		=> array("one\r\ntwo"),
			'line separator'		=> array("a\u{2028}b"),
			'paragraph separator'	=> array("a\u{2029}b"),
			'ampersand'				=> array('Tom & Jerry'),
			'backtick'				=> array('${alert(1)}`'),
			'non-ascii'				=> array('Ёжик 封鎖'),
		);
	}

	//
	// The escaped body has to survive being pasted between two quotes and read
	// back as the value that went in. JSON string syntax is a subset of
	// JavaScript string syntax, so a round trip through json_decode() is the
	// same parse the browser does.
	//
	#[DataProvider('hostileValues')]
	public function testTheEscapedBodyRoundTrips(string $value): void
	{
		$this->assertSame($value, json_decode('"'.forum_js_escape($value).'"'));
	}

	#[DataProvider('hostileValues')]
	public function testNothingThatDelimitsOrClosesTheScriptSurvives(string $value): void
	{
		$escaped = forum_js_escape($value);

		foreach (array('"', "'", '<', '>', '&', "\n", "\r") as $forbidden)
			$this->assertStringNotContainsString($forbidden, $escaped,
				'forum_js_escape() left '.json_encode($forbidden).' in the string literal');
	}

	public function testABackslashCannotEscapeTheClosingQuote(): void
	{
		$this->assertSame('PunBB\\\\', forum_js_escape('PunBB\\'));
	}

	//
	// The negative control: the escaper this replaced hands the backslash
	// straight through, which is the whole finding.
	//
	public function testHtmlencodeIsNotAJavascriptEscaper(): void
	{
		$this->assertSame('PunBB\\', forum_htmlencode('PunBB\\'));
		$this->assertNull(json_decode('"'.forum_htmlencode('PunBB\\').'"'));
	}

	public function testNonStringInputBecomesTheEmptyString(): void
	{
		$this->assertSame('', forum_js_escape(null));
		$this->assertSame('', forum_js_escape(array('x')));
	}

	public function testInvalidUtf8DoesNotCollapseToNothing(): void
	{
		// json_encode() returns false on malformed UTF-8 unless told to
		// substitute; without that the caller would silently emit an empty
		// label instead of the title.
		$this->assertNotSame('', forum_js_escape("caf\xE9"));
	}
}
