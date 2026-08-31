<?php
/**
 * The mysqli drivers escape by the charset they actually talk in.
 *
 * escape() is mysqli_real_escape_string(), which escapes by the charset the
 * client connected with. A bare `SET NAMES` query changes only the server's
 * side of that agreement, so the two drift apart and the 173 escape() calls
 * the forum's SQL is built from stop being escaped by the rules the server
 * reads them under. mysqli_set_charset() moves both at once.
 *
 * Needs a live server, addressed by PUNBB_TEST_MYSQL_*; the test skips without
 * one so the suite stays runnable on a checkout with no MySQL.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class EscapeCharsetTest extends TestCase {
	/** @var array<string, string> */
	private static array $output = array();

	public static function drivers(): array {
		return array('mysqli' => array('mysqli'), 'mysqli_innodb' => array('mysqli_innodb'));
	}

	private function harness(string $driver): string {
		if (!isset(self::$output[$driver]))
		{
			$command = escapeshellarg(PHP_BINARY).
				' -d display_errors=1 -d error_reporting=-1 '.
				escapeshellarg(__DIR__.'/escape_charset_harness.php').' '.
				escapeshellarg($driver).' 2>&1';

			self::$output[$driver] = (string)shell_exec($command);
		}

		if (strpos(self::$output[$driver], 'NO_SERVER') !== false)
			$this->markTestSkipped('no MySQL server: set PUNBB_TEST_MYSQL_HOST');

		return self::$output[$driver];
	}

	/** `utf8` is the server's spelling of `utf8mb3` and MySQL 8 answers with either. */
	private function canonical(string $charset): string {
		return $charset === 'utf8' ? 'utf8mb3' : $charset;
	}

	private function reported(string $output, string $key): string {
		$this->assertSame(1, preg_match('/^'.$key.'=(.+)$/m', $output, $match), $output);

		return trim($match[1]);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testTheDriverConnects(string $driver): void {
		$this->assertStringContainsString('DONE', $this->harness($driver));
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testEscapingAndTheConnectionUseTheSameCharset(string $driver): void {
		$output = $this->harness($driver);

		$this->assertSame(
			$this->canonical($this->reported($output, 'SERVER')),
			$this->canonical($this->reported($output, 'CLIENT')),
			'escape() is escaping by a different charset than the connection reads: '.$output
		);
	}

	/** The connection is the UTF-8 one the forum stores its data in. */
	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testTheConnectionIsUtf8(string $driver): void {
		$this->assertSame('utf8mb3', $this->canonical($this->reported($this->harness($driver), 'SERVER')));
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testNoDiagnosticReachesTheOutput(string $driver): void {
		$output = $this->harness($driver);

		foreach (array('Fatal error', 'Uncaught', 'Parse error', 'Warning:', 'Deprecated:', 'Notice:') as $marker)
			$this->assertStringNotContainsString($marker, $output, $output);
	}
}
