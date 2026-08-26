<?php
/**
 * Input reaching the forum is never slash-escaped: magic quotes were removed
 * in PHP 5.4 and the functions that detected them in PHP 8.0. These tests pin
 * that a value full of quotes and backslashes travels the input-cleaning path
 * and the parser byte for byte.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class MagicQuotesTest extends TestCase {
	private const RAW = 'O\'Reilly "quoted" \\backslash 50% off';

	private array $superglobals = array();

	protected function setUp(): void {
		$this->superglobals = array($_GET, $_POST, $_COOKIE, $_REQUEST);
	}

	protected function tearDown(): void {
		list($_GET, $_POST, $_COOKIE, $_REQUEST) = $this->superglobals;
	}

	public function testRawValueSurvivesInputCleaning(): void {
		$_GET = array('q' => self::RAW);
		$_POST = array('req_message' => self::RAW, 'nested' => array('deep' => self::RAW));
		$_COOKIE = array('c' => self::RAW);
		$_REQUEST = array('r' => self::RAW);

		forum_remove_bad_characters();

		$this->assertSame(self::RAW, $_GET['q']);
		$this->assertSame(self::RAW, $_POST['req_message']);
		$this->assertSame(self::RAW, $_POST['nested']['deep']);
		$this->assertSame(self::RAW, $_COOKIE['c']);
		$this->assertSame(self::RAW, $_REQUEST['r']);
	}

	public function testParseMessageKeepsQuotesAndBackslashes(): void {
		$errors = array();

		$this->assertSame(
			'<p>O&#039;Reilly &quot;quoted&quot; \\backslash 50% off</p>',
			parse_message(preparse_bbcode(forum_trim(self::RAW), $errors), '0')
		);
		$this->assertSame(array(), $errors);
	}

	public function testParseSignatureKeepsQuotesAndBackslashes(): void {
		$errors = array();

		$this->assertSame(
			'O&#039;Reilly &quot;quoted&quot; \\backslash 50% off',
			parse_signature(preparse_bbcode(forum_trim(self::RAW), $errors, true))
		);
		$this->assertSame(array(), $errors);
	}

	/**
	 * The [url] label still goes through stripslashes() in the parser
	 * (`include/parser.php:639`) — unrelated to magic quotes and out of scope
	 * for this plan. Pinned here so a later change to it is a deliberate one.
	 */
	public function testUrlLabelIsStillUnslashedByTheParser(): void {
		$errors = array();

		$this->assertSame(
			'<p><a href="http://example.com/">ab</a></p>',
			parse_message(preparse_bbcode(forum_trim('[url=http://example.com/]a\\b[/url]'), $errors), '0')
		);
	}

	public function testMagicQuotesHelpersAreGone(): void {
		$root = dirname(__DIR__, 4).'/';

		foreach (array('stripslashes_array', 'unescape') as $function)
		{
			$this->assertFalse(function_exists($function), $function.'() still exists');

			foreach (array('include/common.php', 'admin/install.php', 'admin/db_update.php') as $file)
				$this->assertStringNotContainsString($function, file_get_contents($root.$file), $file);
		}
	}
}
