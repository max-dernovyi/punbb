<?php
/**
 * Driver dispatch after the ext/mysql and SQLite2 drivers were removed.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DriverDispatchTest extends TestCase {
	private const SUPPORTED = array('mysqli', 'mysqli_innodb', 'pgsql', 'sqlite3');
	private const REMOVED = array('mysql' => 'mysqli', 'mysql_innodb' => 'mysqli_innodb', 'sqlite' => 'sqlite3');

	private function dispatcher(): string {
		return file_get_contents(FORUM_ROOT.'include/dblayer/common_db.php');
	}

	/** Runs the dispatcher for $db_type in a fresh process and returns its output. */
	private function dispatch(string $db_type): string {
		$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/dispatch_harness.php').' '.escapeshellarg($db_type).' 2>&1';
		return (string)shell_exec($command);
	}

	public function testSupportedTypesAreTheFourSurvivingDrivers(): void {
		$this->assertSame(self::SUPPORTED, forum_supported_db_types());
	}

	public function testEverySupportedDriverHasACaseAndAFile(): void {
		$dispatcher = $this->dispatcher();

		foreach (self::SUPPORTED as $db_type)
		{
			$this->assertStringContainsString('case \''.$db_type.'\':', $dispatcher, $db_type.' has no case in common_db.php');
			$this->assertFileExists(FORUM_ROOT.'include/dblayer/'.$db_type.'.php');
		}
	}

	public function testRemovedDriversAreGone(): void {
		$dispatcher = $this->dispatcher();

		foreach (array_keys(self::REMOVED) as $db_type)
		{
			$this->assertStringNotContainsString('case \''.$db_type.'\':', $dispatcher, $db_type.' still has a case in common_db.php');
			$this->assertFileDoesNotExist(FORUM_ROOT.'include/dblayer/'.$db_type.'.php');
		}
	}

	public function testRemovedDriversMapToTheirReplacement(): void {
		foreach (self::REMOVED as $db_type => $replacement)
			$this->assertSame($replacement, forum_removed_db_type_replacement($db_type));
	}

	public function testSupportedAndUnknownTypesHaveNoReplacement(): void {
		foreach (self::SUPPORTED as $db_type)
			$this->assertNull(forum_removed_db_type_replacement($db_type));

		$this->assertNull(forum_removed_db_type_replacement('oracle'));
		$this->assertNull(forum_removed_db_type_replacement(''));
	}

	/** @return list<array{string, string}> */
	public static function removedDriverProvider(): array {
		$cases = array();
		foreach (self::REMOVED as $db_type => $replacement)
			$cases[] = array($db_type, $replacement);

		return $cases;
	}

	#[DataProvider('removedDriverProvider')]
	public function testDispatchingARemovedDriverNamesTheReplacement(string $db_type, string $replacement): void {
		$output = $this->dispatch($db_type);

		$this->assertStringContainsString('was removed along with the PHP extension', $output);
		$this->assertStringContainsString('\''.$replacement.'\'', $output);
		$this->assertStringNotContainsString('DISPATCHED', $output, 'the dispatcher must stop, not fall through');
	}

	public function testDispatchingAnUnknownDriverListsTheSupportedTypes(): void {
		$output = $this->dispatch('oracle');

		$this->assertStringContainsString('is not a valid database type', $output);
		foreach (self::SUPPORTED as $db_type)
			$this->assertStringContainsString($db_type, $output);

		$this->assertStringNotContainsString('DISPATCHED', $output);
	}

	public function testDbUpdateGuardsRemovedDriversBeforeLoadingTheDblayer(): void {
		$db_update = file_get_contents(FORUM_ROOT.'admin/db_update.php');

		$guard = strpos($db_update, 'forum_removed_db_type_replacement($db_type)');
		$dblayer = strpos($db_update, 'include/dblayer/common_db.php');

		$this->assertNotFalse($guard, 'admin/db_update.php has no removed-driver guard');
		$this->assertLessThan($dblayer, $guard, 'the guard must run before the dblayer is loaded');
	}
}
