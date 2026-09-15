<?php
/**
 * The output sites the cross-site-scripting walk found unescaped, and the
 * script contexts where HTML escaping is the wrong escaper.
 *
 * Each site concatenated a stored value into markup with no forum_htmlencode(),
 * beside a sibling line that did escape the very next field. The ban's IP
 * list is escaped by the bans module's templates: BansControllerTest pins it;
 * a search result's forum name by the search module's: SearchControllerTest
 * pins it; the name an address resolves to by the moderation's controller:
 * ModerateControllerTest pins it; the category heading of a profile's
 * moderator checklist by the profile's sections: ProfileControllerTest pins it.
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
	// The JavaScript contexts. Both put a value inside a string literal in an
	// inline <script>, where HTML escaping is the wrong escaper: see
	// JsEscapeTest.
	//
	public static function javascriptSites(): array
	{
		return array(
			'responsive-nav labels'	=> array('include/PunBB/Module/Layout/Chrome/Layout.php', 3),
			'PUNBB.env'				=> array('include/PunBB/Module/Layout/Chrome/PageChrome.php', 4),
		);
	}

	#[DataProvider('javascriptSites')]
	public function testTheJavascriptContextsUseTheJavascriptEscaper(string $file, int $values): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.$file);

		$this->assertSame($values, substr_count($source, 'Html::script('),
			$file.': the inline script no longer escapes every value for the JavaScript context');
	}

	//
	// The labels the layout escapes for a script have to stay inside the string
	// literals the escaper assumes; if one moves out, the escaping is aimed at
	// the wrong context.
	//
	#[DataProvider('templates')]
	public function testTheResponsiveNavLabelsSitInsideJavascriptStringLiterals(string $template): void
	{
		$source = (string) file_get_contents(FORUM_ROOT.$template);

		foreach (array('nav_board_title', 'nav_menu_admin', 'nav_menu_profile') as $label)
			$this->assertStringContainsString('label: "<?= $'.$label.' ?>"', $source);
	}

	public static function templates(): array
	{
		return array(
			array('include/PunBB/Module/Layout/templates/chrome/main.phtml'),
			array('include/PunBB/Module/Layout/templates/chrome/admin.phtml'),
		);
	}
}
