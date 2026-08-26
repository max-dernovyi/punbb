<?php
/**
 * Keeps README, CLAUDE.md and the ChangeLog in sync with the drivers and the
 * PHP version the code actually supports.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

class DocsDriverListTest extends TestCase {
	private const REMOVED = array('mysql', 'mysql_innodb', 'sqlite');

	/** FORUM_MIN_PHP_VERSION is a full x.y.z; the docs name the release as x.y. */
	private function release(): string {
		return implode('.', array_slice(explode('.', FORUM_MIN_PHP_VERSION), 0, 2));
	}

	private function doc(string $name): string {
		$path = FORUM_ROOT.$name;
		$this->assertFileExists($path);
		return file_get_contents($path);
	}

	public function testReadmeRequiresTheMinimumPhpVersion(): void {
		$this->assertStringContainsString('PHP '.$this->release().' or later', $this->doc('README.md'));
	}

	public function testReadmeListsEverySupportedDriver(): void {
		$readme = $this->doc('README.md');

		foreach (forum_supported_db_types() as $db_type)
			$this->assertStringContainsString('`'.$db_type.'`', $readme, $db_type.' is not listed in README.md');
	}

	public function testReadmeTellsAdminsOnARemovedDriverWhatToDo(): void {
		$readme = $this->doc('README.md');

		foreach (self::REMOVED as $db_type)
			$this->assertStringContainsString('`'.$db_type.'`', $readme, $db_type.' removal is not documented in README.md');

		$this->assertStringContainsString('db_update.php', $readme);
	}

	public function testClaudeMdDocumentsTheDbUpdateGuard(): void {
		$claude = $this->doc('CLAUDE.md');

		$this->assertStringContainsString('forum_removed_db_type_replacement()', $claude);
		$this->assertStringContainsString('admin/db_update.php', $claude);
	}

	public function testChangeLogHasAnEntryForThisRelease(): void {
		$changelog = $this->doc('ChangeLog');

		$this->assertStringStartsWith('PunBB 1.5.0-php84', $changelog);
		$this->assertStringContainsString('PHP '.$this->release(), $changelog);

		foreach (self::REMOVED as $db_type)
			$this->assertStringContainsString('\''.$db_type.'\'', $changelog, $db_type.' removal is not in the ChangeLog');
	}
}
