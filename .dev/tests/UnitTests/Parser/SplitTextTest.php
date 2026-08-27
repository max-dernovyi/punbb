<?php
/**
 * split_text() over text that carries no delimiter at all.
 *
 * The [code] chunker built $inside only inside its token loop, so a message
 * without a [code] tag left the variable undefined — an "Undefined variable"
 * warning on PHP 8 and a null reaching str_replace(). The suite runs with
 * failOnWarning, so a regression fails this test by itself.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class SplitTextTest extends TestCase {
	public function testTextWithoutTheStartDelimiterYieldsAnEmptyInside(): void {
		$errors = array();

		list($inside, $outside) = split_text('plain text, no code tag', '[code]', '[/code]', $errors);

		$this->assertSame(array(), $inside);
		$this->assertSame(array('plain text, no code tag'), $outside);
		$this->assertSame(array(), $errors);
	}

	public function testRetabbingTextWithoutTheStartDelimiterDoesNotWarn(): void {
		global $forum_config;

		$saved = $forum_config['o_indent_num_spaces'] ?? null;
		$forum_config['o_indent_num_spaces'] = 4;

		try {
			$errors = array();
			list($inside, $outside) = split_text("a\tb", '[code]', '[/code]', $errors);

			$this->assertSame(array(), $inside);
			$this->assertSame(array("a\tb"), $outside);
		} finally {
			$forum_config['o_indent_num_spaces'] = $saved;
		}
	}

	public function testDelimitedTextIsSplitIntoInsideAndOutside(): void {
		$errors = array();

		list($inside, $outside) = split_text('a[code]x[/code]b', '[code]', '[/code]', $errors, false);

		$this->assertSame(array('x'), $inside);
		$this->assertSame(array('a', 'b'), $outside);
	}

	public function testAnUnbalancedDelimiterIsReportedAsAnError(): void {
		global $lang_common;

		$errors = array();
		list($inside, $outside) = split_text('a[code]x', '[code]', '[/code]', $errors);

		$this->assertNull($inside);
		$this->assertSame(array('a[code]x'), $outside);
		$this->assertSame(array($lang_common['BBCode code problem']), $errors);
	}
}
