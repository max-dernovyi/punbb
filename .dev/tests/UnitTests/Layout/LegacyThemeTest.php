<?php
/**
 * Pages still built on $tpl_main, served through the bridge on a scratch
 * forum: the chrome's own templates render what the templates of 1.4 did, a
 * legacy .tpl theme renders through the same regions, and extension code at
 * the header and footer points runs where it always ran — the markup points at
 * their positions in the about region.
 *
 * The Legacy fixture style is the 1.4 default templates with the stylesheet
 * script that went with them, so a page in it must match the page in Oxygen
 * byte for byte, but for the style's name.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once FORUM_ROOT.'.dev/tests/UnitTests/Extensions/ScratchForum.php';

class LegacyThemeTest extends TestCase {
	private const PROBE = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<extension engine="1.0">
	<id>layout_probe</id>
	<title>Layout probe</title>
	<version>1.0</version>
	<description>Runs at the header and footer points.</description>
	<author>PunBB test suite</author>
	<minversion>1.4</minversion>
	<maxtestedon>1.5</maxtestedon>
	<hooks>
		<hook id="hd_pre_template_loaded"><![CDATA[$GLOBALS['layout_probe_template'] = basename($tpl_path);]]></hook>
		<hook id="hd_template_loaded"><![CDATA[$tpl_main = str_replace('<div id="brd-head" class="gen-content">', '<!-- probe_marker --><!-- forum_include "probe_note.php" --><div id="brd-head" class="gen-content">', $tpl_main);]]></hook>
		<hook id="hd_head"><![CDATA[$forum_head['probe'] = '<meta name="probe" content="'.$GLOBALS['layout_probe_template'].'" />';]]></hook>
		<hook id="hd_gen_elements"><![CDATA[$gen_elements['<!-- probe_marker -->'] = '<p id="probe-marker">'.FORUM_PAGE_TYPE.'</p>'; $gen_elements['<!-- forum_desc -->'] = '<p id="brd-desc">Probed</p>';]]></hook>
		<hook id="hd_visit_elements"><![CDATA[$visit_links['probe'] = '<span id="visit-probe">'.count($visit_links).'</span>';]]></hook>
		<hook id="hd_alert"><![CDATA[$alert_items['probe'] = '<p id="probe-alert">Probe alert</p>';]]></hook>
		<hook id="hd_main_elements"><![CDATA[unset($main_elements['<!-- forum_crumbs_end -->']);]]></hook>
		<hook id="hd_end"><![CDATA[$forum_page['probe_header'] = defined('FORUM_HEADER') ? 'after FORUM_HEADER' : 'before';]]></hook>
		<hook id="ft_about_output_start"><![CDATA[?><i>start</i><?php]]></hook>
		<hook id="ft_about_pre_quickjump"><![CDATA[echo '<i>pre_quickjump</i>';]]></hook>
		<hook id="ft_about_pre_copyright"><![CDATA[?><i>pre_copyright</i><?php]]></hook>
		<hook id="ft_about_end"><![CDATA[echo '<i>end:', $forum_page['probe_header'], '</i>';]]></hook>
		<hook id="ft_debug_output_start"><![CDATA[echo '<b>debug start</b>';]]></hook>
		<hook id="ft_js_include"><![CDATA[$forum_loader->add_js('var layout_probe = 1;', array('type' => 'inline'));]]></hook>
		<hook id="ft_end"><![CDATA[$tpl_main = str_replace('</body>', '<!-- probe end --></body>', $tpl_main);]]></hook>
	</hooks>
</extension>
XML;

	private const PAGES = array('index.php' => array(), 'admin/index.php' => array(), 'help.php' => array('section' => 'bbcode'), 'userlist.php' => array());

	private static ?ScratchForum $forum = null;

	private static int $admin = 0;

	private static string $log = '';

	public static function setUpBeforeClass(): void {
		if (!class_exists('SQLite3'))
			return;

		self::$forum = new ScratchForum();
		self::$forum->logTo(self::$log = (string) tempnam(sys_get_temp_dir(), 'punbb_layout_'));
		self::$forum->debug(false);
		self::$forum->addStyle('Legacy');
		self::$forum->writeUserInclude('probe_note.php', '<p id="probe-note"><?php echo $forum_user[\'username\'] ?></p>');

		self::$admin = (int) self::$forum->rows('SELECT id FROM users WHERE group_id='.FORUM_ADMIN)[0]['id'];

		// The jump list is only rendered for two forums or more
		self::$forum->rows('INSERT INTO forums (forum_name, cat_id, disp_position) SELECT \'Second forum\', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM forums WHERE forum_name=\'Second forum\')');
	}

	public static function tearDownAfterClass(): void {
		self::$forum?->remove();
		self::$forum = null;

		if (self::$log !== '')
			unlink(self::$log);
	}

	private function forum(): ScratchForum {
		if (self::$forum === null)
			$this->markTestSkipped('the scratch forum needs ext-sqlite3');

		return self::$forum;
	}

	/** @param array<string, string> $get */
	private function page(string $style, string $script, array $get = array()): string {
		$this->forum()->rows('UPDATE users SET style=\''.$style.'\' WHERE id='.self::$admin);

		$page = $this->forum()->request($script, $get);

		foreach (array('Fatal error', 'Warning:', 'Deprecated:', 'Notice:', 'Sorry! The page could not be loaded.') as $diagnostic)
			$this->assertStringNotContainsString($diagnostic, $page, $page);

		return $page;
	}

	/** @return array<string, array{string, array<string, string>}> */
	public static function pageProvider(): array {
		$pages = array();
		foreach (self::PAGES as $script => $get)
			$pages[$script] = array($script, $get);

		return $pages;
	}

	/** @param array<string, string> $get */
	#[DataProvider('pageProvider')]
	public function testALegacyThemeRendersThePageTheChromesOwnTemplateDoes(string $script, array $get): void {
		$oxygen = $this->page('Oxygen', $script, $get);
		$legacy = $this->page('Legacy', $script, $get);

		$this->assertStringContainsString('<div id="brd-messages" class="brd">', $oxygen);
		$this->assertStringContainsString('user_style: "Oxygen"', $oxygen);
		$this->assertSame(str_replace('user_style: "Oxygen"', 'user_style: "Legacy"', $oxygen), $legacy);
	}

	/** A 1.4 redirect.tpl left the regions redirect() never filled as markers; the theme's still does. */
	public function testALegacyThemesRedirectTemplateRendersTheRedirect(): void {
		$pages = array();
		foreach (array('Oxygen', 'Legacy') as $style)
		{
			$this->forum()->rows('UPDATE users SET style=\''.$style.'\' WHERE id='.self::$admin);
			$pages[$style] = $this->forum()->submit('delete.php', array('id' => '1'), array('cancel' => '1'));

			$this->assertStringContainsString('<div id="brd-redirect" class="brd">', $pages[$style], $pages[$style]);
			$this->assertStringNotContainsString('Warning:', $pages[$style]);
		}

		$this->assertStringContainsString('<span>Operation cancelled. Redirecting…</span>', $pages['Oxygen']);
		$this->assertSame(str_replace(array("<body>\n", "</div>\n</div>\n</body>"), array("<body>\n<!-- forum_messages -->\n", "</div>\n</div>\n<!-- forum_javascript -->\n</body>"), $pages['Oxygen']), $pages['Legacy']);
	}

	public function testExtensionCodeRunsAtTheHeaderAndFooterPointsInEitherTemplate(): void {
		$forum = $this->forum();
		$forum->writeExtension('layout_probe', self::PROBE);
		$forum->submit('admin/extensions.php', array('install' => 'layout_probe'), array('install_comply' => '1'));

		foreach (array('Oxygen' => 'main.phtml', 'Legacy' => 'main.tpl') as $style => $template)
		{
			$page = $this->page($style, 'index.php');

			$this->assertStringContainsString('<meta name="probe" content="'.$template.'" />', $page, 'hd_pre_template_loaded saw the template, hd_head added an entry');
			$this->assertStringContainsString('<p id="probe-marker">basic-page</p><p id="probe-note">admin</p><div id="brd-head" class="gen-content">', $page, 'a marker and a user include hd_template_loaded added are filled');
			$this->assertStringContainsString('<p id="brd-desc">Probed</p>', $page);
			$this->assertStringContainsString('<span id="visit-probe">3</span></p>', $page);
			$this->assertStringContainsString('<!-- forum_crumbs_end -->', $page, 'a region hd_main_elements removed leaves its marker');
			$this->assertSame(1, preg_match('#<div id="brd-about">\s*(.*?)\s*</div>\s*<!-- forum_debug -->#s', $page, $about), $page);
			$this->assertMatchesRegularExpression('#^<i>start</i><i>pre_quickjump</i><form id="qjump".*</form>\s*<i>pre_copyright</i>\s*<p id="copyright">.*</p>\s*<i>end:before</i>$#s', $about[1]);
			$this->assertStringContainsString('var layout_probe = 1;', $page);
			$this->assertStringEndsWith("<!-- probe end --></body>\n</html>", rtrim($page), 'ft_end changed the page');
		}

		$admin = $this->page('Legacy', 'admin/index.php');
		$this->assertStringContainsString('<p id="probe-alert">Probe alert</p>', $admin, 'the administration\'s index lists the alert hd_alert added');

		$forum->debug(true);
		$this->assertStringContainsString('<b>debug start</b><p id="querytime"', $this->page('Oxygen', 'index.php'));
	}
}
