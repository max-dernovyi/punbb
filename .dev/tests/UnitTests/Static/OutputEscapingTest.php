<?php
/**
 * The output sites the cross-site-scripting walk found unescaped.
 *
 * Each of them concatenates a stored value into markup with no
 * forum_htmlencode(), in every case beside a sibling line that does escape the
 * very next field — they are omissions, not decisions. None is reachable by a
 * guest: three carry admin-entered data and one carries whatever the resolver
 * returns for a PTR record. They are pinned here because the pages need a live
 * forum to render and the omission is invisible from the rendered page as long
 * as nobody types a `<`.
 *
 * Every pattern is checked against the shape the line had before the fix, so a
 * guard that stopped matching fails instead of passing silently.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OutputEscapingTest extends TestCase
{
	//
	// file => [ what the value is, the fixed line, the line as it was ]
	//
	public static function escapedSites(): array
	{
		return array(
			// The category heading of the moderator-assignment checklist, one
			// line above the forum name it does escape.
			'profile.php category legend' => array(
				'profile.php',
				'\'<legend><span>\'.forum_htmlencode($cur_forum[\'cat_name\']).\':</span></legend>\'',
				'\'<legend><span>\'.$cur_forum[\'cat_name\'].\':</span></legend>\'',
			),
			// The "Posted in" link of a search result, two lines above the
			// escaped last-poster name.
			'search.php forum link' => array(
				'search.php',
				'\'">\'.forum_htmlencode($cur_set[\'forum_name\']).\'</a></li>\'',
				'\'">\'.$cur_set[\'forum_name\'].\'</a></li>\'',
			),
			// gethostbyaddr() returns whatever the PTR record says, and the
			// record for an address belongs to whoever runs its reverse zone.
			'moderate.php hostname lookup' => array(
				'moderate.php',
				', forum_htmlencode(@gethostbyaddr($ip)),',
				', @gethostbyaddr($ip),',
			),
			// The ban's IP list, redisplayed in the edit form...
			'admin/bans.php ip field' => array(
				'admin/bans.php',
				'if (isset($ban_ip)) echo forum_htmlencode($ban_ip);',
				'if (isset($ban_ip)) echo $ban_ip;',
			),
			// ...and on the ban list, between two escaped siblings.
			'admin/bans.php ip listing' => array(
				'admin/bans.php',
				'\'</span> <strong>\'.forum_htmlencode($cur_ban[\'ip\']).\'</strong></li>\'',
				'\'</span> <strong>\'.$cur_ban[\'ip\'].\'</strong></li>\'',
			),
		);
	}

	#[DataProvider('escapedSites')]
	public function testTheSiteEscapes(string $file, string $fixed, string $unfixed): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.$file);

		$this->assertStringContainsString($fixed, $source,
			$file.': the escaped output site is gone — retarget this guard');
		$this->assertStringNotContainsString($unfixed, $source,
			$file.': the value reaches the page unescaped');
	}

	//
	// The negative control: the "unfixed" needle has to be able to find the
	// line it describes, otherwise assertStringNotContainsString above passes
	// for the wrong reason.
	//
	#[DataProvider('escapedSites')]
	public function testTheGuardWouldSeeTheUnescapedLine(string $file, string $fixed, string $unfixed): void
	{
		$unwrapped = preg_replace('#forum_htmlencode\(((?:[^()]|\([^()]*\))*)\)#', '$1',
			(string) file_get_contents(FORUM_ROOT.$file));

		$this->assertStringContainsString($unfixed, (string) $unwrapped,
			$file.': the two needles do not describe the same line');
	}

	//
	// The JavaScript contexts. Both put a value inside a string literal in an
	// inline <script>, where forum_htmlencode() is the wrong escaper: see
	// JsEscapeTest.
	//
	public static function javascriptSites(): array
	{
		return array(
			'board title in the responsive-nav label' => array('style/Oxygen/Oxygen.php', 1),
			'PUNBB.env'								  => array('footer.php', 6),
		);
	}

	#[DataProvider('javascriptSites')]
	public function testTheJavascriptContextsUseTheJavascriptEscaper(string $file, int $values): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.$file);

		$this->assertSame($values, substr_count($source, 'forum_js_escape('),
			$file.': the inline script no longer escapes every value for the JavaScript context');
	}

	//
	// The template markers those two files substitute have to stay inside the
	// string literals the escaper assumes; if one moves out, the escaping is
	// aimed at the wrong context.
	//
	#[DataProvider('templates')]
	public function testTheBoardTitleMarkerSitsInsideAJavascriptStringLiteral(string $template): void
	{
		$this->assertStringContainsString('label: "<!-- forum_board_title -->"',
			(string) file_get_contents(FORUM_ROOT.$template));
	}

	public static function templates(): array
	{
		return array(
			array('include/template/main.tpl'),
			array('include/template/admin.tpl'),
		);
	}
}
