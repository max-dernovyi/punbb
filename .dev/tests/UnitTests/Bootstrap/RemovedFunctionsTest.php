<?php
/**
 * Guards the class of bug plan 02 fixes: calls to functions PHP 7/8 removed.
 *
 * PHPStan level 0 only reports these when its signature map has dropped the
 * symbol. It still carries `get_magic_quotes_runtime` and the ext/sqlite
 * functions, so `make stan` stays silent on them — this test covers the gap.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class RemovedFunctionsTest extends TestCase {
	private const ROOT = __DIR__.'/../../../../';

	/** Functions removed in PHP 7.0/8.0. Calling any of them is fatal on 8.4. */
	private const REMOVED = array(
		'create_function',
		'each',
		'ereg', 'eregi', 'ereg_replace', 'eregi_replace', 'split', 'spliti',
		'get_magic_quotes_gpc', 'get_magic_quotes_runtime', 'set_magic_quotes_runtime',
		'mysql_connect', 'mysql_query', 'mysql_fetch_assoc', 'mysql_select_db',
		'sqlite_open', 'sqlite_query', 'sqlite_fetch_array', 'sqlite_escape_string',
	);

	/** Trees PHPStan also skips: third-party code and generated files. */
	private const SKIP = array('vendor', '.git', '.dev', 'cache', 'extensions', 'img', 'lang', 'style');

	/**
	 * @return array<string, list<string>> file => list of "line: function"
	 */
	private function scan(): array {
		$pattern = '/(?<![\w$\'">-])('.implode('|', self::REMOVED).')\s*\(/';
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
			foreach (file($file->getPathname()) as $number => $line) {
				// function_exists('foo') guards are declarations of intent, not calls.
				if (preg_match($pattern, $line, $match))
					$found[$relative][] = ($number + 1).': '.$match[1];
			}
		}

		ksort($found);
		return $found;
	}

	public function testNoRemovedFunctionIsCalled(): void {
		$found = $this->scan();

		$report = '';
		foreach ($found as $file => $hits)
			$report .= "\n  ".$file.' — '.implode(', ', $hits);

		$this->assertSame(array(), $found, 'Functions removed in PHP 7/8 are still called:'.$report);
	}
}
