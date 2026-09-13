<?php
/**
 * A create_table() field without allow_null is NOT NULL, silently.
 *
 * 1.4 extensions leave the key out, and 1.4.4 read the undefined index as null
 * behind an E_NOTICE essentials.php hid. PHP 8 raises E_WARNING there, which it
 * does not hide, so every driver reads the absent key as false itself.
 * mysqli and pgsql need a live server and skip without one.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CreateTableNullabilityTest extends TestCase {
	/** @var array<string, string> */
	private static array $output = array();

	public static function drivers(): array {
		return array(
			'mysqli'		=> array('mysqli'),
			'mysqli_innodb'	=> array('mysqli_innodb'),
			'pgsql'			=> array('pgsql'),
			'sqlite3'		=> array('sqlite3')
		);
	}

	private function harness(string $driver): string {
		if (!isset(self::$output[$driver]))
		{
			$command = escapeshellarg(PHP_BINARY).
				' -d display_errors=1 -d error_reporting=-1 '.
				escapeshellarg(__DIR__.'/create_table_nullability_harness.php').' '.
				escapeshellarg($driver).' 2>&1';

			self::$output[$driver] = (string)shell_exec($command);
		}

		if (strpos(self::$output[$driver], 'NO_SERVER') !== false)
			$this->markTestSkipped('no server for '.$driver);

		$this->assertStringContainsString('DONE', self::$output[$driver], 'the harness died before finishing: '.self::$output[$driver]);

		return self::$output[$driver];
	}

	#[DataProvider('drivers')]
	public function testAFieldWithoutAllowNullIsNotNull(string $driver): void {
		$output = $this->harness($driver);

		$this->assertStringContainsString('COLUMNS=id:NOT NULL,size:NOT NULL,ip:NOT NULL,nullable:NULL,required:NOT NULL'."\n", $output, $output);
		$this->assertStringContainsString('GONE=false', $output, $output);
	}

	#[DataProvider('drivers')]
	public function testNoDiagnosticReachesTheOutput(string $driver): void {
		$output = $this->harness($driver);

		foreach (array('Fatal error', 'Uncaught', 'Parse error', 'Warning:', 'Deprecated:', 'Notice:') as $marker)
			$this->assertStringNotContainsString($marker, $output, $output);
	}
}
