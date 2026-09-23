<?php
/**
 * A statement the database refuses through the new core's connection ends the
 * forum's transaction as a refused DBLayer query does: rolled back, not
 * committed by the error page closing the connection. A rollback the updater
 * asks for discards the transaction's rows and leaves one open for the rest of
 * the request. The MyISAM driver has no transaction, and keeps what it wrote.
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

class ConnectionRollbackTest extends TestCase {
	/** @return array<string, array{string, int}> the driver, and the rows it keeps */
	public static function drivers(): array {
		return array(
			'mysqli'		=> array('mysqli', 1),
			'mysqli_innodb'	=> array('mysqli_innodb', 0),
			'pgsql'			=> array('pgsql', 0),
			'sqlite3'		=> array('sqlite3', 0),
		);
	}

	/** The harness output, once it ran to the end. */
	private function harness(string $driver, string $mode): string {
		$command = escapeshellarg(PHP_BINARY).' -d display_errors=1 -d error_reporting=-1 '.escapeshellarg(__DIR__.'/connection_rollback_harness.php').' '.escapeshellarg($driver).' '.escapeshellarg($mode).' 2>&1';
		$output = (string) shell_exec($command);

		if ($output === 'NO_SERVER')
			$this->markTestSkipped('no server for '.$driver.': set PUNBB_TEST_*_HOST');

		$this->assertStringEndsWith('DONE', $output, $output);
		$this->assertStringContainsString("WRITTEN\n", $output);

		foreach (array('Fatal error', 'Uncaught', 'Warning:', 'Deprecated:', 'Notice:') as $marker)
			$this->assertStringNotContainsString($marker, $output);

		return $output;
	}

	#[DataProvider('drivers')]
	public function testARefusedStatementLeavesItsTransactionUncommitted(string $driver, int $kept): void {
		$output = $this->harness($driver, 'refused');

		$this->assertStringNotContainsString('NOT REACHED', $output);
		$this->assertStringContainsString('ROWS='.$kept."\n", $output, $output);
	}

	#[DataProvider('drivers')]
	public function testARollbackDiscardsTheRowsAndLeavesATransactionOpen(string $driver, int $kept): void {
		$this->assertStringContainsString('ROWS='.($kept + 1)."\n", $this->harness($driver, 'rolled_back'));
	}
}
