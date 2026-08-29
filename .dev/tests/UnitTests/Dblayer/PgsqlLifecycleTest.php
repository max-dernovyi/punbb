<?php
/**
 * The pgsql driver's result/connection lifecycle on PHP 8.1+.
 *
 * ext/pgsql returns PgSql\Result and PgSql\Connection objects since PHP 8.1,
 * where it used to return resources. That broke the driver in two ways: it
 * keyed an array by the result, and it called pg_free_result()/pg_close() on
 * already-closed handles. Both are fatal now; this pins the fixes.
 *
 * A failing query is not exercised here: the driver now renders the forum
 * error page and exits instead of returning false, which DbErrorRouteTest
 * covers.
 *
 * Needs a live server, addressed by PUNBB_TEST_PGSQL_*; the test skips without
 * one so the suite stays runnable on a checkout with no PostgreSQL.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class PgsqlLifecycleTest extends TestCase {
	private static ?string $output = null;

	/**
	 * Runs the harness once in a fresh process. Out of process because the
	 * driver declares class DBLayer, the name every other driver also uses.
	 */
	private function harness(): string {
		if (self::$output === null)
		{
			$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/pgsql_harness.php').' 2>&1';
			self::$output = (string)shell_exec($command);
		}

		if (strpos(self::$output, 'NO_SERVER') !== false)
			$this->markTestSkipped('no PostgreSQL server: set PUNBB_TEST_PGSQL_HOST');

		return self::$output;
	}

	private function assertHarnessReports(string $expected): void {
		$output = $this->harness();

		$this->assertStringContainsString('DONE', $output, 'the harness died before finishing: '.$output);
		$this->assertStringContainsString($expected, $output);
	}

	public function testTheDriverSurvivesAQueryOnPhp81Objects(): void {
		$this->assertHarnessReports('SELECT=ok');
	}

	/** pg_free_result() on an already-freed PgSql\Result throws; close() must absorb it. */
	public function testFreeingAResultTwiceReportsFailureInsteadOfThrowing(): void {
		$this->assertHarnessReports('FREE_FIRST=true');
		$this->assertHarnessReports('FREE_SECOND=false');
	}

	/** insert_id() reads the recorded text of the last query — the array key that used to fatal. */
	public function testInsertIdReportsTheSequenceOfTheLastInsert(): void {
		$this->assertHarnessReports('INSERT_ID=1');
		$this->assertHarnessReports('AFFECTED=1');
	}

	public function testALaterInsertStillReportsItsOwnId(): void {
		$this->assertHarnessReports('INSERT_ID_AGAIN=2');
	}

	/** An empty result set: result() reports failure, the contract every driver shares. */
	public function testResultReportsFailureForAnEmptyResultSet(): void {
		$this->assertHarnessReports('EMPTY_RESULT=false');
	}

	/** pg_close() on an already-closed PgSql\Connection throws; close() must absorb it. */
	public function testClosingTheConnectionTwiceReportsFailureInsteadOfThrowing(): void {
		$this->assertHarnessReports('CLOSE_FIRST=true');
		$this->assertHarnessReports('CLOSE_SECOND=false');
	}
}
