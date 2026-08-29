<?php
/**
 * Where a database failure goes on PHP 8.1+.
 *
 * mysqli stopped returning false in PHP 8.1 and throws mysqli_sql_exception
 * instead, which uncaught prints a stack trace carrying the connection
 * arguments — verified during the MySQL 8 install sweep. Every driver now
 * catches its own failures and routes them into error(), so the three failure
 * shapes below all end on the forum's error page, reporting the call site the
 * query came from rather than a line inside the driver.
 *
 * mysqli and pgsql need a live server (PUNBB_TEST_MYSQL_* / PUNBB_TEST_PGSQL_*)
 * and skip without one; sqlite3 always runs.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class DbErrorRouteTest extends TestCase {
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

	/** Runs one driver in one failure mode in a fresh process. */
	private function harness(string $driver, string $mode): string {
		$key = $driver.'/'.$mode;

		if (!isset(self::$output[$key]))
		{
			$command = escapeshellarg(PHP_BINARY).
				' -d display_errors=1 -d error_reporting=-1 '.
				escapeshellarg(__DIR__.'/error_route_harness.php').' '.
				escapeshellarg($driver).' '.escapeshellarg($mode).' 2>&1';

			self::$output[$key] = (string)shell_exec($command);
		}

		if (strpos(self::$output[$key], 'NO_SERVER') !== false)
			$this->markTestSkipped('no server for '.$driver);

		return self::$output[$key];
	}

	private function assertNoRawDiagnostic(string $output): void {
		foreach (array('Uncaught', 'Fatal error', 'Stack trace', 'Warning:', 'Deprecated:', 'Notice:') as $marker)
			$this->assertStringNotContainsString($marker, $output, $output);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testASuccessfulQueryStillReturnsItsResult(string $driver): void {
		$output = $this->harness($driver, 'success');

		$this->assertStringContainsString('SELECT=1', $output, $output);
		$this->assertStringContainsString('DONE', $output, $output);
		$this->assertNoRawDiagnostic($output);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testABadQueryRendersTheForumErrorPage(string $driver): void {
		$output = $this->harness($driver, 'bad_sql');

		$this->assertStringContainsString('Sorry! The page could not be loaded.', $output, $output);
		$this->assertNoRawDiagnostic($output);
	}

	/** The message and the failing SQL are what makes the page useful in debug mode. */
	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testABadQueryReportsWhatTheDatabaseSaid(string $driver): void {
		$output = $this->harness($driver, 'bad_sql');

		$this->assertStringContainsString('Database reported:', $output, $output);
		$this->assertStringContainsString('a_table_that_does_not_exist', $output, $output);
		$this->assertStringContainsString('Failed query:', $output, $output);
	}

	/** The reported location is the query's call site, not a line inside the driver. */
	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testABadQueryReportsTheCallSiteNotTheDriver(string $driver): void {
		$output = $this->harness($driver, 'bad_sql');

		$this->assertStringContainsString('error_route_harness.php', $output, $output);
		$this->assertStringNotContainsString('include/dblayer/', $output, $output);
	}

	/**
	 * The install path: admin/install.php builds the schema through the DDL
	 * builders, and a failure there used to escape as an uncaught exception
	 * with a stack trace instead of the installer's error page.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testASchemaErrorRendersTheForumErrorPage(string $driver): void {
		$output = $this->harness($driver, 'bad_ddl');

		$this->assertStringContainsString('Sorry! The page could not be loaded.', $output, $output);
		$this->assertStringContainsString('Database reported:', $output, $output);
		$this->assertNoRawDiagnostic($output);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testAFailedConnectionRendersTheForumErrorPage(string $driver): void {
		$output = $this->harness($driver, 'bad_connect');

		$this->assertStringContainsString('Sorry! The page could not be loaded.', $output, $output);
		$this->assertStringNotContainsString('CONNECTED', $output, $output);
		$this->assertNoRawDiagnostic($output);
	}

	/** A connection that never opened has no query log; error() used to fatal on it. */
	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testAFailedConnectionNamesTheServerItCouldNotReach(string $driver): void {
		$output = $this->harness($driver, 'bad_connect');
		$expected = ($driver == 'sqlite3') ? 'Unable to open database' : 'Unable to connect to';

		$this->assertStringContainsString($expected, $output, $output);
	}
}
