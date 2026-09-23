<?php
/**
 * What a real database reports is what the modules declare: synchronized into
 * an empty database, the declared schema leaves the differ nothing to ask for
 * on any driver, and a gap opened in it is found and closed again.
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

class InstalledSchemaTest extends TestCase {
	/** @var array<string, string> the harness output per driver */
	private static array $output = array();

	/** @return array<string, array{string}> */
	public static function drivers(): array {
		return array(
			'mysqli'		=> array('mysqli'),
			'mysqli_innodb'	=> array('mysqli_innodb'),
			'pgsql'			=> array('pgsql'),
			'sqlite3'		=> array('sqlite3'),
		);
	}

	private function harness(string $driver): string {
		if (!isset(self::$output[$driver]))
		{
			$command = escapeshellarg(PHP_BINARY).' -d display_errors=1 -d error_reporting=-1 '.escapeshellarg(__DIR__.'/installed_schema_harness.php').' '.escapeshellarg($driver).' 2>&1';
			self::$output[$driver] = (string) shell_exec($command);
		}

		if (self::$output[$driver] === 'NO_SERVER')
			$this->markTestSkipped('no server for '.$driver.': set PUNBB_TEST_*_HOST');

		$this->assertStringEndsWith('DONE', self::$output[$driver], 'the harness died before finishing: '.self::$output[$driver]);

		return self::$output[$driver];
	}

	/** @return list<string> the changes the harness reported at $point */
	private function changes(string $driver, string $point): array {
		preg_match_all('/^'.$point.' (.+)$/m', $this->harness($driver), $matches);

		return $matches[1];
	}

	#[DataProvider('drivers')]
	public function testEveryDeclaredTableIsCreated(string $driver): void {
		$this->assertStringContainsString("CREATED:21\n", $this->harness($driver));
	}

	#[DataProvider('drivers')]
	public function testAFreshSchemaLeavesNothingToChange(string $driver): void {
		$this->assertSame(array(), $this->changes($driver, 'FRESH'));
	}

	#[DataProvider('drivers')]
	public function testAGapIsFoundInDeclaredOrder(string $driver): void {
		$gap = array(
			'drop index online.user_id_idx',
			'drop index online.ident_idx',
			'add index online.ident_idx ('.($driver === 'pgsql' || $driver === 'sqlite3' ? 'ident' : 'ident(40)').')',
			'alter column users.password to VARCHAR(255) NOT NULL DEFAULT \'\'',
			'add column users.auto_notify TINYINT(1)',
			'alter column users.registered to INT(10) UNSIGNED NOT NULL DEFAULT \'0\'',
			'drop column users.save_pass',
			'alter column forum_perms.forum_id to INT(10) NOT NULL DEFAULT \'0\'',
			'create table forum_subscriptions',
			'add index topics.last_post_idx (last_post)',
		);

		// SQLite ignores a VARCHAR's length, so the narrowed password is no change there
		if ($driver === 'sqlite3')
			unset($gap[3]);

		$this->assertSame(array_values($gap), $this->changes($driver, 'GAP'));
	}

	#[DataProvider('drivers')]
	public function testTheGapIsClosed(string $driver): void {
		$this->assertStringContainsString("CLOSED:0\n", $this->harness($driver));
	}

	/** An altered column keeps the indexes and the primary key over it. */
	#[DataProvider('drivers')]
	public function testAnAlterKeepsTheIndexAndTheKeyOverTheColumn(string $driver): void {
		$this->assertStringContainsString("KEPT index users.registered_idx: registered\n", $this->harness($driver));
		$this->assertStringContainsString("KEPT primary key forum_perms: group_id,forum_id\n", $this->harness($driver));
	}

	/** A SQLite table with a primary key and a unique key survives a second rebuild. */
	public function testASqliteTableIsRebuiltTwice(): void {
		$this->assertStringContainsString("REBUILT:0\n", $this->harness('sqlite3'));
	}

	#[DataProvider('drivers')]
	public function testNoDiagnosticReachesTheOutput(string $driver): void {
		foreach (array('Fatal error', 'Uncaught', 'Warning:', 'Deprecated:', 'Notice:') as $marker)
			$this->assertStringNotContainsString($marker, $this->harness($driver));
	}
}
