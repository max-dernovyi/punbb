<?php
/**
 * The mysqli drivers' result/connection lifecycle on PHP 8.
 *
 * mysqli_query() returns bool(true) for every non-SELECT statement, and PHP 8
 * throws a TypeError when that reaches mysqli_free_result() and an Error on an
 * already-freed result or an already-closed connection. close() runs at the end
 * of every request (footer.php, plus __destruct() in mysqli_innodb), so an
 * unguarded free there fatals any page whose last query was a write.
 *
 * Needs a live server, addressed by PUNBB_TEST_MYSQL_*; the test skips without
 * one so the suite stays runnable on a checkout with no MySQL.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class MysqliLifecycleTest extends TestCase {
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
				escapeshellarg(__DIR__.'/mysqli_harness.php').' '.
				escapeshellarg($driver).' 2>&1';

			self::$output[$driver] = (string)shell_exec($command);
		}

		if (strpos(self::$output[$driver], 'NO_SERVER') !== false)
			$this->markTestSkipped('no MySQL server: set PUNBB_TEST_MYSQL_HOST');

		return self::$output[$driver];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testTheDriverRunsAQueryCycleToCompletion(string $driver): void {
		$output = $this->harness($driver);

		$this->assertStringContainsString('DONE', $output, $output);
		$this->assertStringContainsString('INSERT_ID=1', $output, $output);
		$this->assertStringContainsString('ROW=o\'reilly', $output, $output);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testClosingTwiceIsSafeAndTheSecondCallReportsFalse(string $driver): void {
		$this->assertStringContainsString("bool(true)\nbool(false)", $this->harness($driver));
	}

	/**
	 * mysqli_query() returns bool(true) for a write, and a truthiness guard let
	 * that reach mysqli_fetch_assoc(), a TypeError on PHP 8 that @ cannot
	 * suppress. Every reader reports failure instead.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testTheResultReadersRefuseTheBoolAWriteReturns(string $driver): void {
		$output = $this->harness($driver);

		$this->assertStringContainsString('WRITE_RESULT=true', $output, $output);

		foreach (array('READ_ASSOC', 'READ_ROW', 'READ_NUM_ROWS', 'READ_RESULT') as $reader)
			$this->assertStringContainsString($reader.'=false', $output, $output);
	}

	/** An empty result set: result() reports failure instead of indexing bool(false). */
	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testResultReportsFailureForAnEmptyResultSet(string $driver): void {
		$this->assertStringContainsString('EMPTY_RESULT=false', $this->harness($driver));
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('drivers')]
	public function testNoDiagnosticReachesTheOutput(string $driver): void {
		$output = $this->harness($driver);

		foreach (array('Fatal error', 'Uncaught', 'Parse error', 'Warning:', 'Deprecated:', 'Notice:') as $marker)
			$this->assertStringNotContainsString($marker, $output, $output);
	}
}
