<?php
/**
 * The installer and the updater through the front controller, on a scratch
 * forum the installer installed: what each answers on an installed board, an
 * update of a board at this release with one module set back, and an update
 * of a board set back to 1.4.4, without module versions, from its form
 * through each data patch to its finish.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\Framework\Modules\ModuleTree;

require_once FORUM_ROOT.'.dev/tests/UnitTests/Extensions/ScratchForum.php';

class SetupRoutesTest extends TestCase {
	private static ?ScratchForum $forum = null;

	public static function setUpBeforeClass(): void {
		self::$forum = new ScratchForum();
	}

	public static function tearDownAfterClass(): void {
		self::$forum?->remove();
		self::$forum = null;
	}

	private static function forum(): ScratchForum {
		self::assertNotNull(self::$forum);

		return self::$forum;
	}

	/** @return array<string, ?string> */
	private static function config(): array {
		$config = array();
		foreach (self::forum()->rows('SELECT conf_name, conf_value FROM config') as $row)
			$config[(string) $row['conf_name']] = $row['conf_value'] !== null ? (string) $row['conf_value'] : null;

		return $config;
	}

	public function testTheInstallerWasDrivenThroughTheFrontControllerAndStoredTheBoard(): void {
		$config = self::config();

		$this->assertSame(array(FORUM_VERSION, (string) FORUM_DB_REVISION, 'English'), array($config['o_cur_version'], $config['o_database_revision'], $config['o_default_lang']));
		$this->assertSame(array(array('id' => 2, 'group_id' => 1, 'username' => 'admin')), self::forum()->rows('SELECT id, group_id, username FROM users WHERE id=2'));
		$this->assertStringStartsWith('$2y$', (string) self::forum()->rows('SELECT password FROM users WHERE id=2')[0]['password'], 'the administrator\'s password is stored as every password is');
		$this->assertNotSame(array(), self::forum()->rows('SELECT word_id FROM search_matches WHERE post_id=1'), 'the welcome post is in the search index');
	}

	/** @return list<array{name: string, schema_version: string, data_version: string}> every module at the version it declares, by name */
	private static function declaredVersions(): array {
		$declared = array();
		foreach (ModuleRegistry::discover(ModuleTree::core(FORUM_ROOT))->modules() as $module)
			$declared[$module->name()] = array('name' => $module->name(), 'schema_version' => $module->version(), 'data_version' => $module->version());

		ksort($declared);

		return array_values($declared);
	}

	/** Nothing to upgrade after a fresh install: every module at the version it declares, schema and data. */
	public function testTheInstallerRecordedEveryModuleAtItsDeclaredVersion(): void {
		$this->assertSame(self::declaredVersions(), self::forum()->rows('SELECT name, schema_version, data_version FROM modules ORDER BY name'));
	}

	public function testAnInstalledBoardAnswersTheInstallerWithItsIndex(): void {
		$output = self::forum()->requestAsGuest('admin/install.php');

		$this->assertSame('The file \'config.php\' already exists which would mean that PunBB is already installed. You should go <a href="../index.php">here</a> instead.', $output);
	}

	public function testAnUpToDateBoardAnswersTheUpdaterWithTheErrorPage(): void {
		$output = self::forum()->requestAsGuest('admin/db_update.php');

		$this->assertStringContainsString('<title>Error - My PunBB forum</title>', $output);
		$this->assertStringContainsString('<p>Your database is already as up-to-date as this script can make it.</p>', $output);
	}

	/** The other tests read the board at this release: a failure halfway through must not leave it at 1.4.4. */
	protected function tearDown(): void {
		if (self::$forum === null)
			return;

		self::$forum->rows('UPDATE config SET conf_value=\''.FORUM_VERSION.'\' WHERE conf_name=\'o_cur_version\'');
		self::$forum->rows('UPDATE config SET conf_value=\''.FORUM_DB_REVISION.'\' WHERE conf_name=\'o_database_revision\'');
	}

	public function testABoardAtThisReleaseBringsUpTheModuleSetBackAndNoOther(): void {
		$forum = self::forum();

		// Censoring recorded a release behind with its table gone; Reports' table gone too, Reports current
		$forum->rows('UPDATE modules SET schema_version=\'1.3.0\', data_version=\'1.3.0\' WHERE name=\'Censoring\'');
		$forum->rows('DROP TABLE censoring');
		$forum->rows('DROP TABLE reports');

		$this->assertStringContainsString('value="Start update"', $forum->requestAsGuest('admin/db_update.php'));

		$start = $forum->requestAsGuest('admin/db_update.php', array('stage' => 'start'));
		$this->assertStringStartsWith("Create table censoring…<br />\n<script", $start);
		$this->assertStringContainsString('window.location="db_update.php?stage=finish"', $start, 'Censoring declares no patch');
		$this->assertStringContainsString('PunBB Database Update completed!', $forum->requestAsGuest('admin/db_update.php', array('stage' => 'finish')));

		$this->assertSame(array(), $forum->rows('SELECT name FROM sqlite_master WHERE name=\'reports\''), 'the table of a module whose schema is current is left as it is');
		$this->assertSame(self::declaredVersions(), $forum->rows('SELECT name, schema_version, data_version FROM modules ORDER BY name'));
		$this->assertStringContainsString('already as up-to-date', $forum->requestAsGuest('admin/db_update.php'));

		// Set back in turn, Reports gets its table
		$forum->rows('UPDATE modules SET schema_version=\'1.3.0\' WHERE name=\'Reports\'');
		$this->assertStringStartsWith("Create table reports…<br />\n<script", $forum->requestAsGuest('admin/db_update.php', array('stage' => 'start')));
		$this->assertStringContainsString('PunBB Database Update completed!', $forum->requestAsGuest('admin/db_update.php', array('stage' => 'finish')));
		$this->assertSame(self::declaredVersions(), $forum->rows('SELECT name, schema_version, data_version FROM modules ORDER BY name'));
	}

	public function testABoardSetBackTo144IsUpdatedFromItsFormToItsFinish(): void {
		$forum = self::forum();
		$forum->rows('UPDATE config SET conf_value=\'1.4.4\' WHERE conf_name=\'o_cur_version\'');
		$forum->rows('UPDATE config SET conf_value=\'4\' WHERE conf_name=\'o_database_revision\'');
		$forum->rows('DELETE FROM config WHERE conf_name IN (\'o_mask_passwords\', \'o_show_moderators\')');
		$forum->rows('UPDATE forums SET num_topics=7, num_posts=9 WHERE id=1');

		// A 1.4.4 board has recorded no data patch, and has no module versions to record
		$forum->rows('DELETE FROM data_patches');
		$forum->rows('DROP TABLE modules');

		$this->assertStringContainsString('value="Start update"', $forum->requestAsGuest('admin/db_update.php'));

		$pages = $page = $forum->requestAsGuest('admin/db_update.php', array('stage' => 'start'));
		$this->assertStringStartsWith("Create table modules…<br />\n<script", $page, 'every module is brought up, and only that table is missing');
		$this->assertStringContainsString('window.location="db_update.php?stage=patch"', $page);

		$requests = 0;
		while (str_contains($page, 'window.location="db_update.php?stage=patch"') && ++$requests <= 50)
			$pages .= $page = $forum->requestAsGuest('admin/db_update.php', array('stage' => 'patch'));

		$this->assertStringContainsString('window.location="db_update.php?stage=finish"', $page);
		$this->assertSame(16, $requests, 'a request a patch');
		$this->assertCount(16, $forum->rows('SELECT name FROM data_patches'));
		$this->assertSame(array(array('conf_value' => '1'), array('conf_value' => '0')), $forum->rows('SELECT conf_value FROM config WHERE conf_name IN (\'o_mask_passwords\', \'o_show_moderators\') ORDER BY conf_name'));

		$finish = $forum->requestAsGuest('admin/db_update.php', array('stage' => 'finish'));
		$this->assertStringContainsString('PunBB Database Update completed!', $finish);
		$this->assertStringContainsString('You may <a href="http://forum.test/index.php">go to the forum index</a> now.', $finish);
		$this->assertDoesNotMatchRegularExpression('/Warning|Notice|Deprecated|Fatal/', $pages.$finish);

		$config = self::config();
		$this->assertSame(array(FORUM_VERSION, (string) FORUM_DB_REVISION), array($config['o_cur_version'], $config['o_database_revision']));
		$this->assertSame(self::declaredVersions(), $forum->rows('SELECT name, schema_version, data_version FROM modules ORDER BY name'), 'every module recorded, as a fresh install records it');
		$this->assertStringContainsString('already as up-to-date', $forum->requestAsGuest('admin/db_update.php'));
		$this->assertSame(array(array('num_topics' => 1, 'num_posts' => 1)), $forum->rows('SELECT num_topics, num_posts FROM forums WHERE id=1'), 'the forums are synchronised');

		$this->assertStringContainsString('Test forum', $forum->request('index.php'), 'the board runs on the updated database');
	}
}
