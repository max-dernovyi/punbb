<?php
/**
 * The module versions a board records, on every driver: none on a board
 * without the table or on a new one, a schema version and a data version
 * written apart, each leaving the other as it was, and a module recorded
 * again updated in place.
 *
 * MySQL and PostgreSQL need a live server, addressed by PUNBB_TEST_*; their
 * cases skip without one.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InstalledVersionsTest extends TestCase {
	/** @return array<string, array{string}> */
	public static function drivers(): array {
		return array(
			'mysqli'		=> array('mysqli'),
			'mysqli_innodb'	=> array('mysqli_innodb'),
			'pgsql'			=> array('pgsql'),
			'sqlite3'		=> array('sqlite3'),
		);
	}

	#[DataProvider('drivers')]
	public function testTheSchemaAndTheDataVersionAreRecordedApart(string $driver): void {
		$command = escapeshellarg(PHP_BINARY).' -d display_errors=1 -d error_reporting=-1 '.escapeshellarg(__DIR__.'/installed_versions_harness.php').' '.escapeshellarg($driver).' 2>&1';
		$output = (string) shell_exec($command);

		if ($output === 'NO_SERVER')
			$this->markTestSkipped('no server for '.$driver.': set PUNBB_TEST_*_HOST');

		$this->assertSame(implode("\n", array(
			'NONE:0',
			'EMPTY:0',
			'SCHEMA:1',
			'SCHEMA Polls 1.1.0 0',
			'DATA:2',
			'DATA Forums 0 1.4.0',
			'DATA Polls 1.1.0 1.0.0',
			'AGAIN:2',
			'AGAIN Forums 0 1.4.0',
			'AGAIN Polls 1.10.0 1.0.0',
			'DONE',
		)), $output);
	}
}
