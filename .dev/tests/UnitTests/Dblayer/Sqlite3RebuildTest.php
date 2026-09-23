<?php
/**
 * SQLite alters no column: add_field(), alter_field() and drop_field() rebuild
 * the table, and every unique key it had must come back with it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Sqlite3RebuildTest extends TestCase {
	private static ?string $output = null;

	private function harness(): string {
		if (self::$output === null)
		{
			$command = escapeshellarg(PHP_BINARY).' -d display_errors=1 -d error_reporting=-1 '.escapeshellarg(__DIR__.'/sqlite3_rebuild_harness.php').' 2>&1';
			self::$output = (string) shell_exec($command);
		}

		$this->assertStringContainsString('DONE', self::$output, 'the harness died before finishing: '.self::$output);

		return self::$output;
	}

	/** @return array<string, array{string}> */
	public static function rebuilds(): array {
		return array('add_field' => array('ADD'), 'alter_field' => array('ALTER'), 'drop_field' => array('DROP'));
	}

	#[DataProvider('rebuilds')]
	public function testEveryUniqueKeySurvivesARebuild(string $step): void {
		$output = $this->harness();

		$this->assertStringContainsString($step.' UNIQUE=2'."\n", $output, $output);
		$this->assertStringContainsString($step.' DUPLICATE_A=refused'."\n", $output, $output);
		$this->assertStringContainsString($step.' DUPLICATE_B=refused'."\n", $output, $output);
	}

	public function testAddFieldQuotesATextDefaultAndKeepsANullOne(): void {
		$output = $this->harness();

		$this->assertStringContainsString('TEXT_DEFAULT=\'English\''."\n", $output, $output);
		$this->assertStringContainsString('NULL_DEFAULT=NULL'."\n", $output, $output);
	}

	public function testNoDiagnosticReachesTheOutput(): void {
		foreach (array('Fatal error', 'Uncaught', 'Parse error', 'Warning:', 'Deprecated:', 'Notice:') as $marker)
			$this->assertStringNotContainsString($marker, $this->harness());
	}
}
