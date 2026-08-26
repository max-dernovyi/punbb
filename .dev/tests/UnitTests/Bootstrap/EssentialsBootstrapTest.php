<?php
/**
 * Guards the first fatal of the PHP 8.4 migration: include/utf8/utf8.php read
 * MB_OVERLOAD_STRING, a constant PHP 8.0 removed along with mbstring function
 * overloading, so every entry point died on its first include.
 *
 * The check runs in a fresh process — the PHPUnit bootstrap has already loaded
 * these files, so an in-process require would prove nothing.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class EssentialsBootstrapTest extends TestCase {
	/** Symptoms of a bootstrap that died rather than reported. */
	private const FATAL = array('Fatal error', 'Uncaught Error', 'Parse error');

	private static function root(): string {
		return dirname(__DIR__, 4).'/';
	}

	/**
	 * Loads $file in a fresh PHP process with FORUM_ROOT set.
	 *
	 * @return string stdout and stderr merged
	 */
	private function load(string $file): string {
		$code = 'define("FORUM_ROOT", '.var_export(self::root(), true).');'
			.'require FORUM_ROOT.'.var_export($file, true).';';

		$command = escapeshellarg(PHP_BINARY)
			.' -d display_errors=1 -d error_reporting=-1 -r '.escapeshellarg($code).' 2>&1';

		$output = array();
		exec($command, $output);

		return implode("\n", $output);
	}

	private function assertNoFatal(string $output, string $file): void {
		foreach (self::FATAL as $symptom)
			$this->assertStringNotContainsString(
				$symptom, $output, $file.' must load without a fatal error, got:'."\n".$output
			);
	}

	public function testUtf8LoaderRunsInAFreshProcess(): void {
		$output = $this->load('include/utf8/utf8.php');

		$this->assertNoFatal($output, 'include/utf8/utf8.php');
		$this->assertSame('', $output, 'the UTF-8 loader must load silently');
	}

	public function testEssentialsBootstrapsInAFreshProcess(): void {
		$output = $this->load('include/essentials.php');

		$this->assertNoFatal($output, 'include/essentials.php');

		// Without config.php the bootstrap has to reach the install redirect;
		// on an installed forum it goes on to the database and prints nothing.
		if (!file_exists(self::root().'config.php'))
			$this->assertStringContainsString('install.php', $output);
	}

	public function testMbstringOverloadConstantIsGone(): void {
		$this->assertStringNotContainsString(
			'MB_OVERLOAD',
			file_get_contents(self::root().'include/utf8/utf8.php'),
			'mbstring function overloading was removed in PHP 8.0'
		);
	}

	public function testPcreUnicodeProbeIsKept(): void {
		$this->assertStringContainsString(
			'PCRE is not compiled with UTF-8 support',
			file_get_contents(self::root().'include/utf8/utf8.php'),
			'the PCRE UTF-8 capability check is still meaningful and must stay'
		);
	}
}
