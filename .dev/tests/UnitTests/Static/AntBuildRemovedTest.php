<?php
/**
 * The Java asset build is gone and nothing in the repository calls it.
 *
 * `.dev/build/` held an Ant script driving YUI Compressor 2.4.5 plus four
 * JARs. Nothing replaced it: the forum serves its JavaScript and stylesheet
 * sources directly, so no asset toolchain of any kind may come back.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AntBuildRemovedTest extends TestCase {
	/** Trees that are not ours, plus the prose that documents the removal. */
	private const SKIP = array('vendor', '.git', 'cache', 'img', 'node_modules', 'docs', 'extensions', 'tmp');

	/** Code and config, not prose — a ChangeLog entry naming Ant is fine. */
	private const EXTENSIONS = array('php', 'xml', 'dist', 'neon', 'json', 'mjs', 'yml', 'yaml', 'sh');

	/**
	 * Traces of the removed build.
	 *
	 * @return array<string, array{string}>
	 */
	public static function traceProvider(): array {
		return array(
			'build tree'    => array('#\.dev/build#'),
			'ant scripts'   => array('#(build|webtasks)\.xml#'),
			'yui'           => array('#yuicompressor|YUIAnt#i'),
			'ant-contrib'   => array('#ant-contrib|bsf\.jar#i'),
		);
	}

	#[DataProvider('traceProvider')]
	public function testNoFileReferencesTheRemovedBuild(string $pattern): void {
		$this->assertSame(array(), $this->scan($pattern));
	}

	public function testTheBuildTreeIsGone(): void {
		$this->assertFileDoesNotExist(FORUM_ROOT.'.dev/build/build.xml');
		$this->assertFileDoesNotExist(FORUM_ROOT.'.dev/build/webtasks.xml');
	}

	public function testNoJarIsShipped(): void {
		$this->assertSame(array(), $this->files(static fn (SplFileInfo $file) => $file->getExtension() === 'jar'));
	}

	/** No asset build at all: neither the Java one nor a JavaScript successor. */
	public function testNoAssetBuildIsInPlace(): void {
		$this->assertFileDoesNotExist(FORUM_ROOT.'.dev/assets/build.mjs');
		$this->assertFileDoesNotExist(FORUM_ROOT.'package.json');
	}

	public function testPhpunitCachesOutsideTheRemovedTree(): void {
		$config = file_get_contents(FORUM_ROOT.'phpunit.xml.dist');

		$this->assertStringContainsString('cacheDirectory=".dev/tmp/phpunit"', $config);
	}

	/**
	 * Every "file:line" in the repository matching $pattern, this test aside.
	 *
	 * @return list<string>
	 */
	private function scan(string $pattern): array {
		$found = array();

		foreach ($this->files(static fn (SplFileInfo $file) => in_array($file->getExtension(), self::EXTENSIONS, true)) as $relative)
			foreach (file(FORUM_ROOT.$relative) as $number => $line)
				if (preg_match($pattern, $line))
					$found[] = $relative.':'.($number + 1);

		sort($found);

		return $found;
	}

	/**
	 * Repository-relative paths of every file the callback accepts, this test aside.
	 *
	 * @return list<string>
	 */
	private function files(callable $accept): array {
		$root = realpath(FORUM_ROOT);
		$self = realpath(__FILE__);
		$found = array();

		$dirs = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
		$filter = new RecursiveCallbackFilterIterator($dirs, static function ($file) use ($root) {
			$relative = ltrim(str_replace($root, '', $file->getPathname()), '/');

			return !in_array(explode('/', $relative)[0], self::SKIP, true);
		});

		foreach (new RecursiveIteratorIterator($filter) as $file) {
			if ($file->getRealPath() === $self || !$accept($file))
				continue;

			$found[] = ltrim(str_replace($root, '', $file->getPathname()), '/');
		}

		sort($found);

		return $found;
	}
}
