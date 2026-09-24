<?php
/**
 * The markup for IE 6, 7 and 8 and the responsive-nav script are gone.
 *
 * No template of the core wraps <html> in conditional comments, nothing the
 * forum serves loads or calls responsive-nav, and Oxygen hides no menu item on
 * a narrow screen, since no script is left to reveal it. A 1.4 theme may still
 * carry both: LegacyThemeTest renders one. `make smoke`, the install matrix,
 * the upgrade path and the user flows assert the same of every page they
 * render, through smoke_dead_markup().
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class DeadMarkupRemovedTest extends TestCase {
	/** Trees that are not the forum's: tests, dependencies, third-party add-ons, prose. */
	private const SKIP = array('.dev', 'vendor', '.git', 'cache', 'img', 'docs', 'extensions', 'modules', 'tmp', '.ralphex', '.claude');

	/** The loader's `browsers` option emits a conditional comment an extension asked for. */
	private const LOADER = 'include/loader.php';

	public static function setUpBeforeClass(): void {
		require_once dirname(__DIR__, 3).'/bin/smoke.php';
	}

	public function testTheScriptIsGone(): void {
		$this->assertFileDoesNotExist(FORUM_ROOT.'style/Oxygen/responsive-nav.min.js');
		$this->assertSame(array(), $this->scan(array('php', 'phtml', 'js', 'css', 'html', 'tpl'), 'responsive-nav'));
	}

	public function testNoCoreTemplateCarriesAConditionalComment(): void {
		$this->assertSame(array(), $this->scan(array('php', 'phtml'), 'an IE conditional comment', array(self::LOADER)));
		$this->assertSame(array('an IE conditional comment'), smoke_dead_markup((string) file_get_contents(FORUM_ROOT.self::LOADER)));
	}

	public function testOxygenHidesNoMenuItemOnANarrowScreen(): void {
		$css = (string) file_get_contents(FORUM_ROOT.'style/Oxygen/Oxygen.css');

		$this->assertStringContainsString('#brd-navlinks li, .main-menu li, .admin-menu li', $css);
		$this->assertDoesNotMatchRegularExpression('#li\s*\+\s*li[^{]*\{[^}]*display\s*:\s*none#', $css);
	}

	/**
	 * Every "file:line" whose line smoke_dead_markup() names $markup in, among
	 * the files with one of $extensions, $except aside.
	 *
	 * @param list<string> $extensions
	 * @param list<string> $except
	 * @return list<string>
	 */
	private function scan(array $extensions, string $markup, array $except = array()): array {
		$root = (string) realpath(FORUM_ROOT);
		$found = array();

		$dirs = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
		$filter = new RecursiveCallbackFilterIterator($dirs, static function ($file) use ($root) {
			$relative = ltrim(str_replace($root, '', $file->getPathname()), '/');

			return !in_array(explode('/', $relative)[0], self::SKIP, true);
		});

		foreach (new RecursiveIteratorIterator($filter) as $file) {
			$relative = ltrim(str_replace($root, '', $file->getPathname()), '/');
			if (!in_array($file->getExtension(), $extensions, true) || in_array($relative, $except, true))
				continue;

			foreach ((array) file($file->getPathname()) as $number => $line)
				if (in_array($markup, smoke_dead_markup($line), true))
					$found[] = $relative.':'.($number + 1);
		}

		sort($found);

		return $found;
	}
}
