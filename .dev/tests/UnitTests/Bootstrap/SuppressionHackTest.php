<?php
/**
 * Guards the PHP 4-era `@` + empty-comment idiom PunBB used to silence
 * diagnostics. It hides exactly the deprecations and warnings this migration
 * has to see, so no served file may carry it again.
 *
 * A plain `@` is still allowed where the failure is genuinely not actionable
 * (a /proc probe outside open_basedir, an unlink of a file already gone).
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class SuppressionHackTest extends TestCase {
	private const ROOT = __DIR__.'/../../../../';

	/** Third-party and generated trees, as in RemovedFunctionsTest. */
	private const SKIP = array('vendor', '.git', 'cache', 'img', 'lang', 'style');

	/**
	 * @return list<string> "file:line" for every remaining occurrence
	 */
	private function scan(): array {
		$root = realpath(self::ROOT);
		$found = array();

		$dirs = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
		$filter = new RecursiveCallbackFilterIterator($dirs, function ($file) use ($root) {
			$relative = ltrim(str_replace($root, '', $file->getPathname()), '/');
			return !in_array(explode('/', $relative)[0], self::SKIP, true);
		});

		foreach (new RecursiveIteratorIterator($filter) as $file) {
			if ($file->getExtension() !== 'php')
				continue;

			$relative = ltrim(str_replace($root, '', $file->getPathname()), '/');
			if ($relative === '.dev/tests/UnitTests/Bootstrap/SuppressionHackTest.php')
				continue;

			foreach (file($file->getPathname()) as $number => $line) {
				if (strpos($line, '@'.'/*'.'*/') !== false)
					$found[] = $relative.':'.($number + 1);
			}
		}

		sort($found);
		return $found;
	}

	public function testNoSuppressionHackRemains(): void {
		$found = $this->scan();

		$this->assertSame(array(), $found, "The `@` + empty-comment idiom is back in:\n".implode("\n", $found));
	}

	/** The scanner has to actually match the idiom it is guarding against. */
	public function testScannerMatchesTheIdiom(): void {
		$fixture = self::ROOT.'.dev/tests/UnitTests/Bootstrap/suppression_probe.php';
		file_put_contents($fixture, "<?php\n\$x = @".'/*'."*/".'unlink("/nope");'."\n");

		try {
			$this->assertContains('.dev/tests/UnitTests/Bootstrap/suppression_probe.php:2', $this->scan());
		} finally {
			unlink($fixture);
		}
	}

	/** The two upload sites now check the return value instead of suppressing. */
	public function testAvatarCallersUseTheCheckedHelper(): void {
		foreach (array('profile.php', 'admin/db_update.php') as $file) {
			$source = file_get_contents(self::ROOT.$file);

			$this->assertSame(0, preg_match('/(?<![\\w$>-])getimagesize\\s*\\(/', $source), $file.' still calls getimagesize() directly');
			$this->assertTrue(strpos($source, 'forum_avatar_size(') !== false, $file.' does not use the checked helper');
		}
	}
}
