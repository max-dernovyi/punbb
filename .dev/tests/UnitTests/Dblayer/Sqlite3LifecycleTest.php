<?php
/**
 * The sqlite3 driver on PHP 8.4: close() is called explicitly by footer.php
 * and again by __destruct(), and SQLite3 throws on an already-closed object.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class Sqlite3LifecycleTest extends TestCase {
	private string $relative = '';
	private string $database = '';
	private string $output = '';

	protected function setUp(): void {
		// The driver prepends FORUM_ROOT to $db_name, so the file has to live
		// inside the forum tree — cache/ is the writable directory it ships with.
		$this->relative = 'cache/punbb_sqlite3_lifecycle_'.getmypid().'.db';
		$this->database = FORUM_ROOT.$this->relative;

		$command = escapeshellarg(PHP_BINARY).
			' -d display_errors=1 -d error_reporting=-1 '.
			escapeshellarg(__DIR__.'/sqlite3_lifecycle_harness.php').' '.
			escapeshellarg($this->relative).' 2>&1';

		$this->output = (string)shell_exec($command);
	}

	protected function tearDown(): void {
		if ($this->database !== '' && file_exists($this->database))
			unlink($this->database);
	}

	public function testTheDriverRunsAQueryCycleToCompletion(): void {
		$this->assertStringContainsString('DONE', $this->output, $this->output);
		$this->assertStringContainsString('INSERT_ID=1', $this->output, $this->output);
		$this->assertStringContainsString('ROW=o\'reilly', $this->output, $this->output);
	}

	public function testClosingTwiceIsSafeAndTheSecondCallReportsFalse(): void {
		$this->assertStringContainsString("bool(true)\nbool(false)", $this->output, $this->output);
	}

	public function testNoDiagnosticReachesTheOutput(): void {
		foreach (array('Fatal error', 'Uncaught', 'Parse error', 'Warning:', 'Deprecated:', 'Notice:') as $marker)
			$this->assertStringNotContainsString($marker, $this->output, $this->output);
	}
}
