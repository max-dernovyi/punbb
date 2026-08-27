<?php
/**
 * The two bundled libraries are gone and nothing points at them any more.
 *
 * `include/utf8/` (phputf8) and `include/idna/` (idna_convert 0.8.0) were
 * replaced by a shim over ext-mbstring and by ext-intl's UTS-46 functions. A
 * file re-appearing under either path, or a `require` of one of them surviving
 * a merge, has to fail the build rather than shadow the replacement.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VendoredLibsRemovedTest extends TestCase {
	/** Trees that are not ours: dependencies, VCS metadata, generated files. */
	private const SKIP = array('vendor', '.git', 'cache', 'img');

	/**
	 * Traces of the removed libraries. Prose naming them is fine; a path or a
	 * constructor is not.
	 *
	 * @return array<string, array{string}>
	 */
	public static function traceProvider(): array {
		return array(
			'utf8 tree'        => array('#include/utf8/#'),
			'idna tree'        => array('#include/idna#'),
			'idna class file'  => array('#idna_convert\.class\.php#'),
			'idna constructor' => array('#new\s+idna_convert#'),
			'phputf8 dispatch' => array('#UTF8_CORE|utf8/(mbstring|native|utils)#'),
		);
	}

	#[DataProvider('traceProvider')]
	public function testNoPhpFileReferencesTheRemovedLibraries(string $pattern): void {
		$this->assertSame(array(), $this->scan($pattern));
	}

	public function testTheVendoredTreesAreGone(): void {
		$this->assertDirectoryDoesNotExist(FORUM_ROOT.'include/utf8');
		$this->assertDirectoryDoesNotExist(FORUM_ROOT.'include/idna');
	}

	public function testTheReplacementsAreInPlace(): void {
		$this->assertFileExists(FORUM_ROOT.'include/utf8.php');
		$this->assertTrue(function_exists('forum_idna_encode'));
		$this->assertTrue(function_exists('forum_idna_decode'));
	}

	/**
	 * Every "file:line" in the repository matching $pattern, this test aside.
	 *
	 * @return list<string>
	 */
	private function scan(string $pattern): array {
		$root = realpath(FORUM_ROOT);
		$self = realpath(__FILE__);
		$found = array();

		$dirs = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
		$filter = new RecursiveCallbackFilterIterator($dirs, static function ($file) use ($root) {
			$relative = ltrim(str_replace($root, '', $file->getPathname()), '/');

			return !in_array(explode('/', $relative)[0], self::SKIP, true);
		});

		foreach (new RecursiveIteratorIterator($filter) as $file) {
			if ($file->getExtension() !== 'php' || $file->getRealPath() === $self)
				continue;

			$relative = ltrim(str_replace($root, '', $file->getPathname()), '/');
			foreach (file($file->getPathname()) as $number => $line)
				if (preg_match($pattern, $line))
					$found[] = $relative.':'.($number + 1);
		}

		sort($found);

		return $found;
	}
}
